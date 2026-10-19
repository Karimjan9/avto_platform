<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MarketplaceNotification extends Model
{
    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = ['id', 'user_id', 'type', 'title', 'data', 'deduplication_key', 'read_at', 'push_status'];

    protected $hidden = ['deduplication_key'];

    protected function casts(): array
    {
        return ['data' => 'array', 'read_at' => 'datetime'];
    }
}
