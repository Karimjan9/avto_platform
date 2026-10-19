<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PartQuote extends Model
{
    protected $fillable = ['part_request_id', 'business_id', 'listing_id', 'price', 'currency', 'message', 'status'];

    protected function casts(): array
    {
        return ['price' => 'decimal:2'];
    }

    public function partRequest(): BelongsTo
    {
        return $this->belongsTo(PartRequest::class);
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function listing(): BelongsTo
    {
        return $this->belongsTo(Listing::class);
    }
}
