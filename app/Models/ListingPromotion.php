<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ListingPromotion extends Model
{
    protected $fillable = ['listing_id', 'created_by', 'starts_at', 'ends_at', 'is_active'];

    protected $hidden = ['created_by'];

    protected function casts(): array
    {
        return ['starts_at' => 'datetime', 'ends_at' => 'datetime', 'is_active' => 'boolean'];
    }
}
