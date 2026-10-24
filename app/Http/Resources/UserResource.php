<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return ['id' => $this->id, 'name' => $this->name, 'email' => $this->email, 'phone' => $this->phone, 'is_admin' => $this->is_admin, 'phone_verified' => $this->hasVerifiedPhone(), 'seller_type' => $this->seller_type, 'seller_ready' => $this->hasCompletedSellerProfile(), 'city' => $this->city];
    }
}
