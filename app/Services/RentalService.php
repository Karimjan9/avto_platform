<?php

namespace App\Services;

use App\Models\Listing;
use App\Models\RentalReservation;
use App\Models\User;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class RentalService
{
    public function active(Builder $query): Builder
    {
        return $query->where(fn ($q) => $q->where('status', 'confirmed')->orWhere(fn ($p) => $p->where('status', 'pending')->where('expires_at', '>', now())));
    }

    public function reserve(Listing $listing, User $user, array $data, bool $block = false): RentalReservation
    {
        return DB::transaction(function () use ($listing, $user, $data, $block) {
            User::whereKey($user->id)->lockForUpdate()->firstOrFail();
            $requestId = $data['client_request_id'] ?? null;
            $hash = hash('sha256', $listing->id.'|'.json_encode($data));
            if ($requestId && ! $block) {
                $previous = RentalReservation::where('user_id', $user->id)->where('client_request_id', $requestId)->first();
                if ($previous) {
                    abort_if($previous->request_hash !== $hash, 409, 'Bu so‘rov kaliti boshqa ma’lumot bilan ishlatilgan.');

                    return $previous;
                }
            }
            $query = $block ? Listing::query() : Listing::published();
            $model = $query->whereKey($listing->id)->lockForUpdate()->firstOrFail();
            abort_unless($model->section->value === 'cars' && $model->offer_type === 'rent', 422, 'Bu arenda e’loni emas.');
            abort_if(! $block && $model->user_id === $user->id, 422, 'O‘z transportingizni xaridor sifatida band qilib bo‘lmaydi.');
            $start = CarbonImmutable::parse($data['starts_at'])->setTimezone(config('app.timezone'));
            $end = CarbonImmutable::parse($data['ends_at'])->setTimezone(config('app.timezone'));
            $days = (int) ceil($start->diffInSeconds($end) / 86400);
            abort_if($days > 365, 422, 'Bandlov muddati ko‘pi bilan 365 kun.');
            $busy = $this->active(RentalReservation::where('listing_id', $model->id))->where('starts_at', '<', $end)->where('ends_at', '>', $start)->exists();
            abort_if($busy, 409, 'Bu vaqt oralig‘i band.');
            $snapshot = null;
            if (! $block) {
                $rental = $model->rentalDetails;
                abort_unless($rental, 422, 'Arenda shartlari hali to‘ldirilmagan.');
                abort_if($days < $rental->min_days, 422, 'Minimal arenda muddati bajarilmadi.');
                abort_if($rental->driver_option !== 'either' && $rental->driver_option !== $data['driver_option'], 422, 'Haydovchi sharti mos emas.');
                $periods = (int) ceil($days / ['day' => 1, 'week' => 7, 'month' => 30][$model->rental_period]);
                $snapshot = ['title' => $model->title, 'price' => $model->price, 'currency' => $model->currency, 'billing_period' => $model->rental_period, 'period_count' => $periods, 'days' => $days, 'total' => Money::format(Money::multiply(Money::cents($model->price), $periods)), 'deposit' => $rental->deposit, 'driver_option' => $data['driver_option'], 'daily_mileage_limit_km' => $rental->daily_mileage_limit_km];
            }
            $reservation = RentalReservation::create(['listing_id' => $model->id, 'user_id' => $block ? null : $user->id, 'source' => $block ? 'owner_block' : 'booking', 'status' => $block ? 'confirmed' : 'pending', 'starts_at' => $start, 'ends_at' => $end, 'expires_at' => $block ? null : now()->addDay(), 'snapshot' => $snapshot, 'private_note' => $data['note'] ?? null]);
            $reservation->update(['client_request_id' => $requestId, 'request_hash' => $hash]);
            if (! $block) {
                $notifier = app(MarketplaceNotifier::class);
                if ($model->business) {
                    $notifier->business($model->business, 'rentals', 'rental_booking', 'Yangi arenda bandlovi', ['reservation_id' => $reservation->id, 'listing_id' => $model->id], 'rental:'.$reservation->id);
                } else {
                    $notifier->user($model->user_id, 'rental_booking', 'Yangi arenda bandlovi', ['reservation_id' => $reservation->id], 'rental:'.$reservation->id);
                }
            }

            return $reservation;
        });
    }

    public function transition(RentalReservation $reservation, string $status): RentalReservation
    {
        return DB::transaction(function () use ($reservation, $status) {
            Listing::withTrashed()->whereKey($reservation->listing_id)->lockForUpdate()->firstOrFail();
            $model = RentalReservation::whereKey($reservation->id)->lockForUpdate()->firstOrFail();
            if ($model->status === $status) {
                return $model;
            }
            abort_if($model->status === 'cancelled' || ! in_array($status, ['confirmed', 'cancelled'], true), 409, 'Bandlov holati mos emas.');
            abort_if($status === 'confirmed' && $model->expires_at?->lte(now()), 409, 'Bandlov muddati tugagan.');
            $model->update(['status' => $status, 'expires_at' => null]);
            if ($model->user_id) {
                app(MarketplaceNotifier::class)->user($model->user_id, 'rental_status', 'Arenda bandlovi yangilandi', ['reservation_id' => $model->id, 'status' => $status], 'rental:'.$model->id.':'.$status);
            }

            return $model;
        });
    }
}
