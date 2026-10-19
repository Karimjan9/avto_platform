<?php

namespace App\Http\Resources;

use App\Services\TrustBadges;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BusinessResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $private = $request->user() && ($request->user()->id === $this->user_id || $request->user()->is_admin);

        return [
            'id' => $this->id, 'type' => $this->type->value, 'name' => $this->name, 'slug' => $this->slug,
            'sections' => $this->resource->sectionCodes(),
            'documents_verified' => TrustBadges::has($this->verifications, 'business_documents'),
            'branches' => $this->branches->where('is_active', true)->map(fn ($branch) => $branch->only(['id', 'name', 'city', 'address', 'phone', 'working_hours']))->values(),
            'description' => $this->description, 'region' => $this->region, 'city' => $this->city,
            'address' => $this->address, 'phone' => $this->phone, 'status' => $this->status,
            'moderation_reason' => $this->when($private, $this->moderation_reason),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
