<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VehicleGeneration extends Model
{
    protected $fillable = ['vehicle_model_id', 'name', 'year_from', 'year_to'];

    protected function casts(): array
    {
        return ['year_from' => 'integer', 'year_to' => 'integer'];
    }

    public function model(): BelongsTo
    {
        return $this->belongsTo(VehicleModel::class, 'vehicle_model_id');
    }
}
