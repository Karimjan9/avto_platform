<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PartFitment extends Model
{
    public $timestamps = false;

    protected $fillable = ['part_product_id', 'vehicle_variant_id', 'year_from', 'year_to'];

    protected function casts(): array
    {
        return ['year_from' => 'integer', 'year_to' => 'integer'];
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(VehicleVariant::class, 'vehicle_variant_id');
    }
}
