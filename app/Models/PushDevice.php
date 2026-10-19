<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PushDevice extends Model
{
    protected $fillable = ['user_id', 'device_id', 'platform', 'token', 'token_hash', 'last_seen_at', 'is_active'];

    protected $hidden = ['token', 'token_hash'];

    protected function casts(): array
    {
        return ['token' => 'encrypted', 'last_seen_at' => 'datetime', 'is_active' => 'boolean'];
    }
}
