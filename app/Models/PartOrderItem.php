<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PartOrderItem extends Model
{
    public $timestamps = false;

    protected $fillable = ['part_order_id', 'listing_id', 'quantity', 'unit_price', 'snapshot'];

    protected function casts(): array
    {
        return ['quantity' => 'integer', 'unit_price' => 'decimal:2', 'snapshot' => 'array'];
    }
}
