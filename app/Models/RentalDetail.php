<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RentalDetail extends Model
{
    public $timestamps = false;

    protected $fillable = ['daily_price', 'weekly_price', 'monthly_price', 'deposit', 'min_days', 'daily_mileage_limit_km', 'driver_option'];

    protected $hidden = ['id', 'listing_id'];

    protected function casts(): array
    {
        return ['daily_price' => 'decimal:2', 'weekly_price' => 'decimal:2', 'monthly_price' => 'decimal:2', 'deposit' => 'decimal:2', 'min_days' => 'integer', 'daily_mileage_limit_km' => 'integer'];
    }
}
