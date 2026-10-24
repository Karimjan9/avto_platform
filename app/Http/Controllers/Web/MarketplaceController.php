<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\WebListingFilterRequest;
use App\Models\Listing;
use App\Models\PartProduct;
use App\Models\User;
use App\Services\ListingSearch;
use App\Services\MarketplacePresentation;
use Illuminate\Pagination\Paginator;

class MarketplaceController extends Controller
{
    public function index(WebListingFilterRequest $request, ListingSearch $search, MarketplacePresentation $presentation)
    {
        $filters = $request->validated();
        $presentationMode = (bool) config('presentation.enabled');
        $presentationCards = [];
        $featuredPart = null;
        $partOffers = collect();
        $curated = collect();
        $homePresentation = $presentationMode && $request->routeIs('home') && empty($filters);
        if ($homePresentation) {
            $cards = config('presentation.cards');
            $curated = Listing::published()->where('description', 'like', MarketplacePresentation::LISTING_DESCRIPTION.'%')
                ->where(function ($query) use ($cards) {
                    foreach ($cards as $card) {
                        $email = config('presentation.owners.'.$card['owner'].'.email');
                        $query->orWhere(fn ($item) => $item->where('title', $card['title'])->whereHas('user', fn ($user) => $user->where('email', $email)));
                    }
                })->setEagerLoads([])->with(['coverImage', 'carDetails', 'partDetails', 'motoDetails'])->get();
            $positions = array_flip(array_column($cards, 'title'));
            $curated = $curated->sortBy(fn ($listing) => $positions[$listing->title])->values();
            $featuredPart = PartProduct::where('identity_key', config('presentation.featured_product.identity_key'))->where('status', 'active')
                ->whereIn('created_by', User::select('id')->where('email', config('presentation.owners.avtoqism.email')))->first();
            if ($featuredPart) {
                $partOffers = Listing::published()->where('part_product_id', $featuredPart->id)
                    ->whereHas('business', fn ($business) => $business->whereIn('slug', array_keys(config('presentation.businesses'))))
                    ->setEagerLoads([])->with(['coverImage', 'partDetails', 'business' => fn ($business) => $business->withoutEagerLoads()])
                    ->orderBy('price')->orderBy('id')->limit(2)->get();
                $partOffers->each(fn ($listing) => $presentation->decorateListing($listing));
            }
        }
        // Cards need one image and a few specifications, not the complete API graph.
        $listings = $curated->isNotEmpty() ? new Paginator($curated, 4, 1, ['path' => $request->url()]) : $search->query($filters)->setEagerLoads([])->with([
            'coverImage', 'carDetails', 'partDetails', 'motoDetails',
        ])->simplePaginate(18)->withQueryString();
        $listings->getCollection()->each(function ($listing) use ($presentation, &$presentationCards) {
            $presentation->decorateListing($listing);
            if ($listing->is_demo) {
                $presentationCards[$listing->id] = $listing->presentation;
            }
        });

        return view('marketplace.index', compact('listings', 'filters', 'presentationMode', 'presentationCards', 'featuredPart', 'partOffers'));
    }

    public function show(string $listing, MarketplacePresentation $presentation)
    {
        $listing = Listing::published()->with(Listing::RELATIONS)->findOrFail($listing);
        $presentation->decorateListing($listing);

        return view('marketplace.show', compact('listing'));
    }
}
