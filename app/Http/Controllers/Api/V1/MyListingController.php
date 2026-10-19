<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\ListingStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\SaveListingRequest;
use App\Http\Resources\ListingResource;
use App\Models\Listing;
use App\Services\ListingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class MyListingController extends Controller
{
    public function index(Request $request)
    {
        $filters = $request->validate(['status' => ['sometimes', Rule::enum(ListingStatus::class)], 'per_page' => ['sometimes', 'integer', 'min:1', 'max:50']]);
        $query = $request->user()->listings()->with(Listing::RELATIONS)->latest('id');
        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        return ListingResource::collection($query->paginate($filters['per_page'] ?? 20)->withQueryString());
    }

    public function store(SaveListingRequest $request, ListingService $service)
    {
        return (new ListingResource($service->save($request->user(), $request->validated())))->response()->setStatusCode(201);
    }

    public function show(Listing $listing): ListingResource
    {
        Gate::authorize('update', $listing);

        return new ListingResource($listing->load(Listing::RELATIONS));
    }

    public function update(SaveListingRequest $request, Listing $listing, ListingService $service): ListingResource
    {
        return new ListingResource($service->save($request->user(), $request->validated(), $listing));
    }

    public function status(Request $request, Listing $listing, ListingService $service): ListingResource
    {
        Gate::authorize('update', $listing);
        $data = $request->validate(['status' => ['required', Rule::in(['draft', 'pending', 'sold', 'archived'])]]);

        return new ListingResource($service->transition($listing, ListingStatus::from($data['status'])));
    }

    public function destroy(Listing $listing)
    {
        Gate::authorize('delete', $listing);
        abort_if($listing->hasFutureReservations(), 422, 'Kelgusi arenda bandlovlari mavjud.');
        $listing->delete();

        return response()->noContent();
    }
}
