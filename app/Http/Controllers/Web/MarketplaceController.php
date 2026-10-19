<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\WebListingFilterRequest;
use App\Models\Listing;
use App\Services\ListingSearch;

class MarketplaceController extends Controller
{
    public function index(WebListingFilterRequest $request, ListingSearch $search)
    {
        $filters = $request->validated();
        // Cards need one image and a few specifications, not the complete API graph.
        $listings = $search->query($filters)->setEagerLoads([])->with([
            'coverImage', 'carDetails', 'partDetails', 'motoDetails',
        ])->simplePaginate(18)->withQueryString();

        return view('marketplace.index', compact('listings', 'filters'));
    }

    public function show(string $listing)
    {
        $listing = Listing::published()->with(Listing::RELATIONS)->findOrFail($listing);

        return view('marketplace.show', compact('listing'));
    }
}
