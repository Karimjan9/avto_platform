<?php

namespace App\Services;

use App\Models\Business;
use App\Models\Listing;

class MarketplacePresentation
{
    public const LISTING_DESCRIPTION = 'Demo namoyish e’loni. Haqiqiy savdo taklifi emas.';

    public function isDemoListing(Listing $listing): bool
    {
        if (str_starts_with($listing->description ?? '', self::LISTING_DESCRIPTION)) {
            return true;
        }
        $owner = $listing->relationLoaded('user') ? $listing->getRelation('user') : null;

        return $owner && str_ends_with($owner->email ?? '', '@demo.buxoroavto.invalid');
    }

    public function listingMetadata(Listing $listing): array
    {
        if (! $this->isDemoListing($listing)) {
            return [];
        }
        foreach (config('presentation.cards', []) as $key => $metadata) {
            if ($metadata['title'] === $listing->title) {
                return ['is_demo' => true, 'key' => $key, 'label' => 'Demo e’lon'] + $metadata;
            }
        }

        return ['is_demo' => true, 'label' => 'Demo e’lon'];
    }

    public function businessMetadata(Business $business): array
    {
        $metadata = config('presentation.businesses.'.$business->slug);

        return $metadata ? ['is_demo' => true] + $metadata : [];
    }

    public function decorateListing(Listing $listing): Listing
    {
        $metadata = $this->listingMetadata($listing);
        $listing->setAttribute('is_demo', (bool) ($metadata['is_demo'] ?? false));
        $listing->setAttribute('presentation', $metadata);
        if ($listing->relationLoaded('business') && $listing->getRelation('business')) {
            $business = $listing->getRelation('business');
            $business->setAttribute('presentation', $this->businessMetadata($business));
        }

        return $listing;
    }
}
