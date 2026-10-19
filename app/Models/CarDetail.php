<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CarDetail extends Model
{
    public $timestamps = false;

    protected $fillable = ['brand', 'model', 'generation', 'trim', 'year', 'mileage_km', 'engine_cc', 'fuel_type', 'transmission', 'body_type', 'drive_type', 'color', 'technical_condition', 'gas_equipment', 'power_kw', 'battery_capacity_kwh', 'range_km', 'charging_types', 'battery_health_percent'];

    protected $hidden = ['id', 'listing_id'];

    protected function casts(): array
    {
        return ['year' => 'integer', 'mileage_km' => 'integer', 'engine_cc' => 'integer', 'power_kw' => 'float', 'battery_capacity_kwh' => 'float', 'range_km' => 'integer', 'charging_types' => 'array', 'battery_health_percent' => 'float'];
    }
}
