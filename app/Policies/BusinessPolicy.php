<?php

namespace App\Policies;

use App\Models\Business;
use App\Models\User;

class BusinessPolicy
{
    public function update(User $user, Business $business): bool
    {
        return $business->user_id === $user->id;
    }

    public function delete(User $user, Business $business): bool
    {
        return $this->update($user, $business);
    }
}
