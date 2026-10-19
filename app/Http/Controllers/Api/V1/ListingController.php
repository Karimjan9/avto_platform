<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\ListingFilterRequest;
use App\Http\Resources\ListingResource;
use App\Models\Listing;
use App\Services\ListingSearch;

class ListingController extends Controller
{
    public function index(ListingFilterRequest $request)
    {
        $filters = $request->validated();

        return ListingResource::collection(app(ListingSearch::class)->query($filters)->paginate($filters['per_page'] ?? 20)->withQueryString());
    }

    public function show(string $listing): ListingResource
    {
        return new ListingResource(Listing::published()->with(Listing::RELATIONS)->findOrFail($listing));
    }
}
