<?php

namespace App\Models;

use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Model;

class PhoneLoginChallenge extends Model
{
    use MassPrunable;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = [];

    protected $hidden = ['code_hash', 'session_hash', 'ip_hash'];

    protected function casts(): array
    {
        return [
            'attempts' => 'integer',
            'bound_user_id' => 'integer',
            'expires_at' => 'immutable_datetime',
            'sent_at' => 'immutable_datetime',
            'failed_at' => 'immutable_datetime',
            'consumed_at' => 'immutable_datetime',
            'revoked_at' => 'immutable_datetime',
        ];
    }

    public function prunable()
    {
        return static::where('created_at', '<=', now()->subDays(2));
    }
}
