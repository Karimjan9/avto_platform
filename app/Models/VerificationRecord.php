<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VerificationRecord extends Model
{
    protected $fillable = ['kind', 'user_id', 'business_id', 'listing_id', 'checked_by', 'evidence_reference', 'verified_value', 'status', 'checked_at', 'expires_at'];

    protected $hidden = ['checked_by', 'evidence_reference', 'verified_value'];

    protected function casts(): array
    {
        return ['checked_at' => 'datetime', 'expires_at' => 'datetime'];
    }
}
