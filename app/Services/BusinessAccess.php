<?php

namespace App\Services;

use App\Models\Business;
use App\Models\BusinessMember;
use App\Models\User;

class BusinessAccess
{
    public function allows(User $user, Business $business, ?string $permission = null): bool
    {
        if ($business->user_id === $user->id) {
            return true;
        }
        $key = 'marketplace.business_permissions.'.$user->id.'.'.$business->id;
        $attributes = request()->attributes;
        if (! $attributes->has($key)) {
            $member = BusinessMember::where('business_id', $business->id)->where('user_id', $user->id)->first();
            $attributes->set($key, $member?->permissions ?? false);
        }
        $permissions = $attributes->get($key);

        return is_array($permissions) && ($permission === null || in_array($permission, $permissions, true));
    }

    public function authorize(User $user, Business $business, ?string $permission = null): void
    {
        abort_unless($this->allows($user, $business, $permission), 403);
    }
}
