<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BusinessBranch extends Model
{
    protected $fillable = ['business_id', 'name', 'city', 'address', 'phone', 'working_hours', 'is_active'];

    protected function casts(): array
    {
        return ['working_hours' => 'array', 'is_active' => 'boolean'];
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }
}
