<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PartQuoteResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return ['id' => $this->id, 'part_request_id' => $this->part_request_id, 'business' => new BusinessResource($this->whenLoaded('business')),
            'listing_id' => $this->listing_id, 'price' => $this->price, 'currency' => $this->currency, 'message' => $this->message,
            'status' => $this->status, 'created_at' => $this->created_at?->toIso8601String()];
    }
}
