<?php

namespace App\Services;

use App\Models\SavedSearch;
use Illuminate\Support\Facades\DB;

class SavedSearchMatcher
{
    public function run(): int
    {
        $count = 0;
        $searches = SavedSearch::where('is_active', true)->orderBy('last_checked_at')->orderBy('id')->limit(100)->get();
        foreach ($searches as $search) {
            $matches = app(ListingSearch::class)->query($search->filters)->reorder()->where('published_at', '>=', $search->created_at)
                ->whereNotExists(fn ($q) => $q->selectRaw('1')->from('saved_search_matches')->where('saved_search_id', $search->id)->whereColumn('listing_id', 'listings.id'))
                ->orderBy('published_at')->orderBy('id')->limit(50)->get();
            foreach ($matches as $listing) {
                $count += DB::transaction(function () use ($search, $listing) {
                    $inserted = DB::table('saved_search_matches')->insertOrIgnore(['saved_search_id' => $search->id, 'listing_id' => $listing->id, 'created_at' => now(), 'updated_at' => now()]);
                    if ($inserted) {
                        app(MarketplaceNotifier::class)->user($search->user_id, 'saved_search', 'Qidiruvingizga mos yangi e’lon', ['saved_search_id' => $search->id, 'listing_id' => $listing->id], 'search:'.$search->id.':listing:'.$listing->id);
                    }

                    return $inserted;
                });
            }
            $search->update(['last_checked_at' => now()]);
        }

        return $count;
    }
}
