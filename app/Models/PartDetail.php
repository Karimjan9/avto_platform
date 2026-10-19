<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PartDetail extends Model
{
    public $timestamps = false;

    protected $fillable = ['category', 'manufacturer', 'part_number', 'oem_number', 'compatible_brands', 'compatible_models', 'compatible_vehicles', 'origin', 'unit', 'quantity_per_set', 'stock_quantity', 'tyre_width_mm', 'tyre_aspect_ratio', 'tyre_diameter_inch', 'tyre_season', 'manufactured_at', 'side', 'position', 'delivery_available', 'pickup_available', 'delivery_terms', 'warranty_months', 'return_days', 'return_terms'];

    protected $hidden = ['id', 'listing_id'];

    protected function casts(): array
    {
        return ['compatible_brands' => 'array', 'compatible_models' => 'array', 'compatible_vehicles' => 'array', 'quantity_per_set' => 'integer', 'stock_quantity' => 'integer', 'tyre_width_mm' => 'integer', 'tyre_aspect_ratio' => 'integer', 'tyre_diameter_inch' => 'integer', 'manufactured_at' => 'date:Y-m-d', 'delivery_available' => 'boolean', 'pickup_available' => 'boolean', 'warranty_months' => 'integer', 'return_days' => 'integer', 'stock_updated_at' => 'datetime', 'stock_version' => 'integer'];
    }
}
