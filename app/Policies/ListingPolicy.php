<?php

namespace App\Policies;

use App\Models\Listing;
use App\Models\User;
use App\Services\BusinessAccess;

class ListingPolicy
{
    public function update(User $user, Listing $listing): bool
    {
        return $listing->user_id === $user->id || ($listing->business && app(BusinessAccess::class)->allows($user, $listing->business, 'catalog'));
    }

    public function delete(User $user, Listing $listing): bool
    {
        return $this->update($user, $listing);
    }
}
