<?php

namespace App\Http\Controllers\Web;

use App\Enums\ListingStatus;
use App\Http\Controllers\Controller;
use App\Models\Business;
use App\Models\Listing;
use App\Models\MarketplaceNotification;
use App\Services\BusinessAccess;
use App\Services\ListingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class CabinetController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $filters = $request->validate(['status' => ['sometimes', Rule::in(['all', ...array_column(ListingStatus::cases(), 'value')])], 'q' => ['nullable', 'string', 'max:120']]);
        $activeStatus = $filters['status'] ?? 'all';
        $search = trim($filters['q'] ?? '');
        $counts = $user->listings()->selectRaw('status, COUNT(*) AS aggregate')->groupBy('status')->pluck('aggregate', 'status');
        $query = $user->listings()->with(['coverImage'])->latest('id');
        if ($activeStatus !== 'all') {
            $query->where('status', $activeStatus);
        }
        if ($search !== '') {
            $query->where('title', 'like', '%'.$search.'%');
        }
        $listings = $query->simplePaginate(12)->withQueryString();
        $businesses = Business::withoutEagerLoads()->where(fn ($q) => $q->where('user_id', $user->id)->orWhereHas('members', fn ($m) => $m->where('user_id', $user->id)))->orderBy('name')->limit(50)->get();
        $notifications = MarketplaceNotification::where('user_id', $user->id)->latest('created_at')->limit(8)->get();

        return view('cabinet.index', compact('counts', 'listings', 'businesses', 'notifications', 'activeStatus', 'search'));
    }

    public function status(Request $request, Listing $listing, ListingService $service)
    {
        Gate::authorize('update', $listing);
        $data = $request->validate(['status' => ['required', Rule::in(['draft', 'pending', 'sold', 'archived'])]]);
        $service->transition($listing, ListingStatus::from($data['status']));

        return redirect()->route('cabinet')->with('status', 'E’lon holati yangilandi.');
    }

    public function listing(Request $request, ?Listing $listing = null)
    {
        if ($listing) {
            Gate::authorize('update', $listing);
            $listing->load(Listing::RELATIONS);
        }
        $businesses = Business::with('user:id,phone,phone_verified_at')->where(fn ($q) => $q->where('user_id', $request->user()->id)->orWhereHas('members', fn ($m) => $m->where('user_id', $request->user()->id)))->limit(50)->get()->filter(fn ($b) => app(BusinessAccess::class)->allows($request->user(), $b, 'catalog'))->values();
        $businesses->each(function ($business) use ($request) {
            $owner = $business->user;
            $business->setAttribute('verified_contact_phone', $owner?->hasVerifiedPhone() && $business->phone === $owner->phone ? $business->phone : $request->user()->phone);
            $business->unsetRelation('user');
        });

        return view('cabinet.listing', compact('listing', 'businesses'));
    }

    public function inventory(Request $request, Business $business, BusinessAccess $access)
    {
        abort_unless($access->allows($request->user(), $business, 'inventory') || $access->allows($request->user(), $business, 'catalog'), 403);

        return view('cabinet.inventory', ['business' => $business, 'canImport' => $access->allows($request->user(), $business, 'catalog'), 'canStock' => $access->allows($request->user(), $business, 'inventory')]);
    }

    public function calendar(Request $request, Listing $listing, BusinessAccess $access)
    {
        abort_unless($listing->user_id === $request->user()->id || ($listing->business && $access->allows($request->user(), $listing->business, 'rentals')), 403);
        abort_unless($listing->section->value === 'cars' && $listing->offer_type === 'rent', 404);

        return view('cabinet.calendar', compact('listing'));
    }

    public function admin()
    {
        Gate::authorize('marketplace-admin');

        return view('cabinet.admin', ['listings' => Listing::with('coverImage')->where('status', 'pending')->latest('id')->simplePaginate(20)]);
    }

    public function review(Listing $listing)
    {
        Gate::authorize('marketplace-admin');
        $listing->load(Listing::RELATIONS);

        return view('marketplace.show', ['listing' => $listing, 'preview' => true]);
    }
}
