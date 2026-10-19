<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PushDelivery extends Model
{
    public $timestamps = false;

    protected $fillable = ['marketplace_notification_id', 'push_device_id', 'sent_at'];

    protected function casts(): array
    {
        return ['sent_at' => 'datetime'];
    }
}
