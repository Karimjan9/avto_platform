<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Listing;
use App\Models\RentalReservation;
use App\Services\BusinessAccess;
use App\Services\RentalService;
use Carbon\Carbon;
use Illuminate\Http\Request;

class RentalController extends Controller
{
    public function availability(Request $request, string $listing, RentalService $service)
    {
        $model = Listing::published()->findOrFail($listing);
        abort_unless($model->offer_type === 'rent', 422, 'Arenda e’loni emas.');
        $data = $request->validate(['from' => ['required', 'date'], 'to' => ['required', 'date', 'after:from']]);
        abort_if(Carbon::parse($data['from'])->diffInDays(Carbon::parse($data['to'])) > 93, 422, 'Kalendar oralig‘i ko‘pi bilan 93 kun.');
        $from = Carbon::parse($data['from'])->setTimezone(config('app.timezone'));
        $to = Carbon::parse($data['to'])->setTimezone(config('app.timezone'));
        $busy = $service->active(RentalReservation::where('listing_id', $model->id))->where('starts_at', '<', $to)->where('ends_at', '>', $from)->orderBy('starts_at')->get(['starts_at', 'ends_at']);

        return response()->json(['data' => ['listing_id' => $model->id, 'busy' => $busy]]);
    }

    private function interval(Request $request, bool $block): array
    {
        $request->validate(['client_request_id' => [$block ? 'prohibited' : 'sometimes', 'uuid']]);
        $data = $request->validate(['starts_at' => ['required', 'date', 'after:now', 'regex:/(Z|[+-]\d{2}:\d{2})$/'], 'ends_at' => ['required', 'date', 'after:starts_at', 'regex:/(Z|[+-]\d{2}:\d{2})$/'], 'driver_option' => [$block ? 'prohibited' : 'required', 'in:with_driver,without_driver'], 'note' => ['nullable', 'string', 'max:1000']]);

        return $data + $request->only('client_request_id');
    }

    private function seller(Request $request, Listing $listing): void
    {
        abort_unless($listing->user_id === $request->user()->id || ($listing->business && app(BusinessAccess::class)->allows($request->user(), $listing->business, 'rentals')), 403);
    }

    public function store(Request $request, string $listing, RentalService $service)
    {
        $model = Listing::published()->findOrFail($listing);

        return response()->json(['data' => $service->reserve($model, $request->user(), $this->interval($request, false))], 201);
    }

    public function block(Request $request, Listing $listing, RentalService $service)
    {
        $this->seller($request, $listing);

        return response()->json(['data' => $service->reserve($listing, $request->user(), $this->interval($request, true), true)], 201);
    }

    public function index(Request $request)
    {
        return RentalReservation::where('user_id', $request->user()->id)->latest('id')->paginate(50);
    }

    public function calendar(Request $request, Listing $listing)
    {
        $this->seller($request, $listing);
        $data = $request->validate(['from' => ['required_with:to', 'date'], 'to' => ['required_with:from', 'date', 'after:from']]);
        $query = RentalReservation::where('listing_id', $listing->id);
        if (isset($data['from'], $data['to'])) {
            $from = Carbon::parse($data['from'])->setTimezone(config('app.timezone'));
            $to = Carbon::parse($data['to'])->setTimezone(config('app.timezone'));
            abort_if($from->diffInDays($to) > 93, 422, 'Kalendar oralig‘i ko‘pi bilan 93 kun.');
            $query->where('starts_at', '<', $to)->where('ends_at', '>', $from);
        }

        return $query->orderByDesc('starts_at')->orderByDesc('id')->paginate(50);
    }

    public function cancel(Request $request, RentalReservation $reservation, RentalService $service)
    {
        abort_unless($reservation->user_id === $request->user()->id && $reservation->source === 'booking', 403);

        return response()->json(['data' => $service->transition($reservation, 'cancelled')]);
    }

    public function status(Request $request, Listing $listing, RentalReservation $reservation, RentalService $service)
    {
        $this->seller($request, $listing);
        abort_unless($reservation->listing_id === $listing->id, 404);
        $data = $request->validate(['status' => ['required', 'in:confirmed,cancelled']]);

        return response()->json(['data' => $service->transition($reservation, $data['status'])]);
    }
}
