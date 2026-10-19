<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VehicleVariant extends Model
{
    protected $fillable = ['vehicle_generation_id', 'name', 'engine_code', 'engine_cc', 'fuel_type', 'power_kw'];

    protected $with = ['generation.model.make'];

    protected function casts(): array
    {
        return ['engine_cc' => 'integer', 'power_kw' => 'float'];
    }

    public function generation(): BelongsTo
    {
        return $this->belongsTo(VehicleGeneration::class, 'vehicle_generation_id');
    }
}
