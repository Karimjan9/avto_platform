<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PartProductResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return ['id' => $this->id, 'title' => $this->title, 'manufacturer' => $this->manufacturer, 'part_number' => $this->part_number,
            'oem_number' => $this->oem_number, 'category' => $this->category, 'attributes' => (object) ($this->attributes ?? []),
            'status' => $this->status, 'fitments' => $this->whenLoaded('fitments'), 'offers_count' => $this->whenCounted('offers'),
            'moderation_reason' => $this->when($request->user() && ($request->user()->is_admin || $request->user()->id === $this->created_by), $this->moderation_reason)];
    }
}
