<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BusinessInquiry extends Model
{
    protected $fillable = ['business_id', 'user_id', 'listing_id', 'message', 'reply', 'status'];

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }
}
