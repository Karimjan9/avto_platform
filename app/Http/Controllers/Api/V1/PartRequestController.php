<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\PartQuoteResource;
use App\Http\Resources\PartRequestResource;
use App\Models\Business;
use App\Models\PartQuote;
use App\Models\PartRequest;
use App\Models\VehicleVariant;
use App\Services\BusinessAccess;
use App\Services\MarketplaceNotifier;
use App\Services\VehicleCatalog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class PartRequestController extends Controller
{
    public function index(Request $request)
    {
        return PartRequestResource::collection(PartRequest::where('user_id', $request->user()->id)->with('variant')->latest('id')->paginate(50));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'vehicle_variant_id' => ['required', 'integer', 'exists:vehicle_variants,id'],
            'vehicle_year' => ['required', 'integer', 'between:1900,'.(now()->year + 1)],
            'title' => ['required', 'string', 'between:3,160'], 'description' => ['required', 'string', 'between:10,5000'],
            'category' => ['required', Rule::in(array_keys(config('marketplace.part_categories')))], 'part_number' => ['nullable', 'string', 'max:80'], 'city' => ['required', 'string', 'max:100'],
        ]);
        if (! app(VehicleCatalog::class)->validYear(VehicleVariant::findOrFail($data['vehicle_variant_id']), $data['vehicle_year'])) {
            throw ValidationException::withMessages(['vehicle_year' => 'Transport yiliga mos variantni tanlang.']);
        }
        abort_if(PartRequest::where('user_id', $request->user()->id)->where('status', 'open')->where('expires_at', '>', now())->count() >= 20, 422, 'Ochiq so‘rovlar limiti 20 ta.');
        $model = PartRequest::create($data + ['user_id' => $request->user()->id, 'status' => 'open', 'expires_at' => now()->addDays(14)]);

        return (new PartRequestResource($model->load('variant')))->response()->setStatusCode(201);
    }

    public function show(Request $request, PartRequest $partRequest)
    {
        abort_unless($partRequest->user_id === $request->user()->id, 403);

        return new PartRequestResource($partRequest->load(['variant', 'quotes.business']));
    }

    public function close(Request $request, PartRequest $partRequest)
    {
        abort_unless($partRequest->user_id === $request->user()->id, 403);
        DB::transaction(function () use ($partRequest) {
            $model = PartRequest::whereKey($partRequest->id)->lockForUpdate()->firstOrFail();
            $model->update(['status' => 'closed']);
            $model->quotes()->where('status', 'pending')->update(['status' => 'rejected']);
        });

        return new PartRequestResource($partRequest->fresh());
    }

    public function businessRequests(Request $request, Business $business, BusinessAccess $access)
    {
        $access->authorize($request->user(), $business, 'requests');
        abort_unless($business->status === 'active' && in_array('parts', $business->sectionCodes(), true), 422, 'Faol zapchast yo‘nalishi talab qilinadi.');
        $data = $request->validate(['category' => ['sometimes', Rule::in(array_keys(config('marketplace.part_categories')))], 'city' => ['sometimes', 'string', 'max:100']]);
        $query = PartRequest::where('status', 'open')->where('expires_at', '>', now())->where('user_id', '!=', $request->user()->id)->with('variant');
        foreach ($data as $field => $value) {
            $query->where($field, $value);
        }

        return PartRequestResource::collection($query->latest('id')->paginate(50));
    }

    public function quote(Request $request, Business $business, PartRequest $partRequest, BusinessAccess $access, MarketplaceNotifier $notifier)
    {
        $access->authorize($request->user(), $business, 'requests');
        abort_unless($business->status === 'active' && in_array('parts', $business->sectionCodes(), true), 422, 'Faol zapchast do‘koni kerak.');
        $data = $request->validate(['price' => ['required', 'numeric', 'decimal:0,2', 'between:0,99999999999999'], 'currency' => ['required', Rule::in(config('marketplace.currencies'))], 'message' => ['required', 'string', 'between:3,2000'], 'listing_id' => ['nullable', 'integer']]);
        if (isset($data['listing_id'])) {
            $offer = $business->listings()->published()->where('section', 'parts')->findOrFail($data['listing_id']);
            abort_if($offer->currency !== $data['currency'], 422, 'Taklif valyutasi mos emas.');
        }
        $quote = DB::transaction(function () use ($business, $partRequest, $data, $notifier) {
            $model = PartRequest::whereKey($partRequest->id)->lockForUpdate()->firstOrFail();
            abort_if($model->status !== 'open' || $model->expires_at->isPast(), 409, 'So‘rov yopilgan yoki muddati tugagan.');
            abort_if($model->user_id === $business->user_id, 422, 'O‘z so‘rovingizga taklif berib bo‘lmaydi.');
            $quote = PartQuote::updateOrCreate(['part_request_id' => $model->id, 'business_id' => $business->id], $data + ['listing_id' => null, 'status' => 'pending']);
            $notifier->user($model->user_id, 'part_quote', 'Detal so‘rovingizga taklif keldi', ['part_request_id' => $model->id, 'quote_id' => $quote->id]);

            return $quote;
        });

        return (new PartQuoteResource($quote->load('business')))->response()->setStatusCode(201);
    }

    public function accept(Request $request, PartRequest $partRequest, PartQuote $quote, MarketplaceNotifier $notifier)
    {
        abort_unless($partRequest->user_id === $request->user()->id, 403);
        abort_unless($quote->part_request_id === $partRequest->id, 404);
        $quote = DB::transaction(function () use ($partRequest, $quote, $notifier) {
            $model = PartRequest::whereKey($partRequest->id)->lockForUpdate()->firstOrFail();
            $offer = PartQuote::with('business')->whereKey($quote->id)->lockForUpdate()->firstOrFail();
            abort_if($model->status !== 'open' || $model->expires_at->isPast() || $offer->status !== 'pending', 409, 'Taklifni qabul qilish mumkin emas.');
            abort_unless($offer->business && $offer->business->status === 'active' && in_array('parts', $offer->business->sectionCodes(), true), 422, 'Biznes faol emas.');
            if ($offer->listing_id) {
                $listing = $offer->business->listings()->published()->whereKey($offer->listing_id)->first();
                abort_unless($listing && $listing->partDetails?->stock_quantity > 0, 409, 'Bog‘langan taklif hozir mavjud emas.');
            }
            $offer->update(['status' => 'accepted']);
            $model->quotes()->where('id', '!=', $offer->id)->where('status', 'pending')->update(['status' => 'rejected']);
            $model->update(['status' => 'closed']);
            $notifier->business($offer->business, 'requests', 'quote_accepted', 'Taklifingiz qabul qilindi', ['part_request_id' => $model->id, 'quote_id' => $offer->id], 'accepted:'.$offer->id);

            return $offer;
        });

        return new PartQuoteResource($quote);
    }
}
