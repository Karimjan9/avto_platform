<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SavedSearch extends Model
{
    protected $fillable = ['user_id', 'name', 'filters', 'is_active', 'last_checked_at'];

    protected function casts(): array
    {
        return ['filters' => 'array', 'is_active' => 'boolean', 'last_checked_at' => 'datetime'];
    }
}
