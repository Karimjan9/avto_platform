<?php

namespace App\Services;

use App\Models\Business;
use App\Models\Listing;
use App\Models\PartOrder;
use App\Models\User;
use App\Support\Money;
use Illuminate\Support\Facades\DB;

class PartOrderService
{
    public function create(User $buyer, array $data): PartOrder
    {
        return DB::transaction(function () use ($buyer, $data) {
            User::whereKey($buyer->id)->lockForUpdate()->firstOrFail();
            $requestId = $data['client_request_id'] ?? null;
            $hash = hash('sha256', json_encode($data));
            if ($requestId) {
                $previous = PartOrder::where('user_id', $buyer->id)->where('client_request_id', $requestId)->first();
                if ($previous) {
                    abort_if($previous->request_hash !== $hash, 409, 'Bu so‘rov kaliti boshqa ma’lumot bilan ishlatilgan.');

                    return $previous->load('items');
                }
            }
            $items = collect($data['items'])->sortBy('listing_id');
            $offers = [];
            $total = 0;
            $businessId = null;
            $branchId = null;
            $currency = null;
            foreach ($items as $item) {
                $offer = Listing::published()->where('section', 'parts')->whereNotNull('business_id')->whereKey($item['listing_id'])->lockForUpdate()->firstOrFail();
                $details = $offer->partDetails()->lockForUpdate()->firstOrFail();
                abort_if($offer->user_id === $buyer->id, 422, 'O‘z mahsulotingizga buyurtma berib bo‘lmaydi.');
                if ($businessId !== null) {
                    abort_if($businessId !== $offer->business_id || $branchId !== $offer->business_branch_id || $currency !== $offer->currency, 422, 'Bir buyurtmada bitta kompaniya, filial va valyuta bo‘lsin.');
                }
                $businessId = $offer->business_id;
                $branchId = $offer->business_branch_id;
                $currency = $offer->currency;
                abort_if($details->stock_quantity < $item['quantity'], 409, 'Omborda yetarli mahsulot yo‘q.');
                abort_if($data['fulfillment'] === 'delivery' ? ! $details->delivery_available : ! $details->pickup_available, 422, 'Tanlangan olish usuli mavjud emas.');
                $lineTotal = Money::multiply(Money::cents($offer->price), $item['quantity']);
                abort_if($total > Money::MAX - $lineTotal, 422, 'Jami narx chegaradan oshdi.');
                $total += $lineTotal;
                $details->stock_quantity -= $item['quantity'];
                $details->stock_version++;
                $details->stock_updated_at = now();
                $details->save();
                $offers[] = [$offer, $details, $item['quantity']];
            }
            $order = PartOrder::create(['user_id' => $buyer->id, 'business_id' => $businessId, 'business_branch_id' => $branchId, 'status' => 'pending', 'currency' => $currency, 'total' => Money::format($total), 'fulfillment' => $data['fulfillment'], 'recipient_name' => $data['recipient_name'], 'contact_phone' => $data['contact_phone'], 'delivery_address' => $data['delivery_address'] ?? null, 'expires_at' => now()->addDay()]);
            $order->update(['client_request_id' => $requestId, 'request_hash' => $hash]);
            foreach ($offers as [$offer, $details, $quantity]) {
                $order->items()->create(['listing_id' => $offer->id, 'quantity' => $quantity, 'unit_price' => $offer->price, 'snapshot' => ['title' => $offer->title, 'part_product_id' => $offer->part_product_id, 'manufacturer' => $details->manufacturer, 'part_number' => $details->part_number, 'origin' => $details->origin, 'unit' => $details->unit, 'quantity_per_set' => $details->quantity_per_set, 'warranty_months' => $details->warranty_months, 'return_days' => $details->return_days, 'return_terms' => $details->return_terms]]);
            }
            app(MarketplaceNotifier::class)->business(Business::findOrFail($businessId), 'orders', 'part_order', 'Yangi zapchast buyurtmasi', ['order_id' => $order->id], 'new-order:'.$order->id);

            return $order->load('items');
        });
    }

    public function transition(PartOrder $order, string $next, ?array $allowedFrom = null): PartOrder
    {
        return DB::transaction(function () use ($order, $next, $allowedFrom) {
            $model = PartOrder::whereKey($order->id)->lockForUpdate()->firstOrFail();
            abort_if($allowedFrom !== null && ! in_array($model->status, $allowedFrom, true), 409, 'Buyurtma holati o‘zgargan.');
            if ($model->status === $next) {
                return $model->load('items');
            }
            $allowed = ['pending' => ['confirmed', 'cancelled'], 'confirmed' => ['ready', 'cancelled'], 'ready' => ['completed', 'cancelled'], 'completed' => [], 'cancelled' => []];
            abort_unless(in_array($next, $allowed[$model->status], true), 409, 'Buyurtma holatini bunday o‘zgartirib bo‘lmaydi.');
            abort_if($next !== 'cancelled' && $model->status === 'pending' && $model->expires_at?->lte(now()), 409, 'Buyurtma rezervi muddati tugagan.');
            if ($next === 'cancelled') {
                foreach ($model->items()->orderBy('listing_id')->get() as $item) {
                    $listing = Listing::withTrashed()->whereKey($item->listing_id)->lockForUpdate()->firstOrFail();
                    $detail = $listing->partDetails()->lockForUpdate()->firstOrFail();
                    $detail->stock_quantity += $item->quantity;
                    $detail->stock_version++;
                    $detail->stock_updated_at = now();
                    $detail->save();
                }
            }
            $model->update(['status' => $next, 'expires_at' => null]);
            app(MarketplaceNotifier::class)->user($model->user_id, 'order_status', 'Buyurtma holati yangilandi', ['order_id' => $model->id, 'status' => $next], 'order:'.$model->id.':'.$next);

            return $model->load('items');
        });
    }

    public function expire(): int
    {
        $count = 0;
        foreach (PartOrder::where('status', 'pending')->where('expires_at', '<=', now())->orderBy('id')->limit(200)->get() as $order) {
            $count += DB::transaction(function () use ($order) {
                $current = PartOrder::whereKey($order->id)->lockForUpdate()->firstOrFail();
                if ($current->status !== 'pending' || ! $current->expires_at || $current->expires_at->isFuture()) {
                    return 0;
                }
                $this->transition($current, 'cancelled');

                return 1;
            });
        }

        return $count;
    }
}
