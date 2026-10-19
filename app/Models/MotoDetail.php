<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MotoDetail extends Model
{
    public $timestamps = false;

    protected $fillable = ['type', 'brand', 'model', 'year', 'mileage_km', 'engine_cc', 'fuel_type', 'transmission', 'abs', 'power_kw', 'battery_voltage_v', 'battery_capacity_ah', 'range_km'];

    protected $hidden = ['id', 'listing_id'];

    protected function casts(): array
    {
        return ['year' => 'integer', 'mileage_km' => 'integer', 'engine_cc' => 'integer', 'transmission' => 'string', 'abs' => 'boolean', 'power_kw' => 'float', 'battery_voltage_v' => 'float', 'battery_capacity_ah' => 'float', 'range_km' => 'integer'];
    }
}
