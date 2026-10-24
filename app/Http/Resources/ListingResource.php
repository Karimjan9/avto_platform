<?php

namespace App\Http\Resources;

use App\Enums\BusinessType;
use App\Services\TrustBadges;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class ListingResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $private = $request->user() && ($request->user()->can('update', $this->resource) || $request->user()->is_admin);
        $relation = $this->resource->detailRelation();

        return [
            'id' => $this->id, 'section' => $this->section->value, 'title' => $this->title,
            'description' => $this->description, 'condition' => $this->condition,
            'offer_type' => $this->offer_type, 'rental_period' => $this->rental_period,
            'deal_terms' => $this->deal_terms ?? ['cash'],
            'seller_type' => $this->business_id ? collect(BusinessType::cases())->first(fn ($type) => $type->section() === $this->section)?->value : 'private',
            'business_id' => $this->business_id,
            'vehicle_variant_id' => $this->vehicle_variant_id, 'part_product_id' => $this->part_product_id, 'business_branch_id' => $this->business_branch_id,
            'vehicle_variant' => $this->whenLoaded('variant'),
            'part_product' => new PartProductResource($this->whenLoaded('partProduct')),
            'rental' => $this->whenLoaded('rentalDetails', fn () => $this->rentalDetails?->toArray()),
            'price' => $this->price, 'currency' => $this->currency, 'is_negotiable' => $this->is_negotiable,
            'region' => $this->region, 'city' => $this->city, 'contact_phone' => $this->contact_phone,
            'status' => $this->status->value,
            'seller' => $this->whenLoaded('user', fn () => ['id' => $this->user->id, 'name' => $this->user->name, 'phone_verified' => TrustBadges::has($this->user->verifications, 'phone', $this->user->phone)]),
            'contact_phone_verified' => $this->whenLoaded('user', fn () => $this->contact_phone === $this->user->phone && TrustBadges::has($this->user->verifications, 'phone', $this->user->phone)),
            'vehicle_inspected' => $this->whenLoaded('verifications', fn () => TrustBadges::has($this->verifications, 'vehicle_inspection')),
            'promotion' => $this->when($this->resource->relationLoaded('promotion'), fn () => $this->promotion && $this->promotion->is_active && $this->promotion->starts_at->lte(now()) && $this->promotion->ends_at->gt(now()) && $this->status->value === 'published' ? ['is_sponsored' => true, 'label' => 'Reklama', 'ends_at' => $this->promotion->ends_at->toIso8601String()] : ['is_sponsored' => false, 'label' => null]),
            'business' => new BusinessResource($this->whenLoaded('business')),
            'details' => $this->whenLoaded($relation, fn () => $this->resource->getRelation($relation)?->toArray()),
            'images' => $this->whenLoaded('images', fn () => $this->images->map(fn ($image) => [
                'id' => $image->id, 'url' => Storage::disk('public')->url($image->path), 'sort_order' => (int) $image->sort_order,
                'thumbnail_url' => $image->thumbnail_path ? Storage::disk('public')->url($image->thumbnail_path) : null,
                'display_url' => $image->display_path ? Storage::disk('public')->url($image->display_path) : null,
                'width' => $image->width, 'height' => $image->height,
            ])),
            'moderation_reason' => $this->when($private, $this->moderation_reason),
            'published_at' => $this->published_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
