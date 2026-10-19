<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PartRequestResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return ['id' => $this->id, 'vehicle_variant_id' => $this->vehicle_variant_id, 'vehicle_year' => $this->vehicle_year,
            'vehicle' => $this->whenLoaded('variant'), 'title' => $this->title, 'description' => $this->description, 'category' => $this->category,
            'part_number' => $this->part_number, 'city' => $this->city, 'status' => $this->status, 'expires_at' => $this->expires_at?->toIso8601String(),
            'quotes' => PartQuoteResource::collection($this->whenLoaded('quotes'))];
    }
}
