<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BusinessSection extends Model
{
    public $timestamps = false;

    protected $fillable = ['business_id', 'section'];
}
