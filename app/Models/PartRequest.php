<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PartRequest extends Model
{
    protected $fillable = ['user_id', 'vehicle_variant_id', 'vehicle_year', 'title', 'description', 'category', 'part_number', 'city', 'status', 'expires_at'];

    protected function casts(): array
    {
        return ['expires_at' => 'datetime', 'vehicle_year' => 'integer'];
    }

    public function quotes(): HasMany
    {
        return $this->hasMany(PartQuote::class);
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(VehicleVariant::class, 'vehicle_variant_id');
    }
}
