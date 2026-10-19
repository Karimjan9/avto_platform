<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PartOrder extends Model
{
    protected $fillable = ['user_id', 'business_id', 'business_branch_id', 'status', 'currency', 'total', 'fulfillment', 'recipient_name', 'contact_phone', 'delivery_address', 'expires_at', 'client_request_id', 'request_hash'];

    protected $hidden = ['request_hash'];

    protected function casts(): array
    {
        return ['total' => 'decimal:2', 'expires_at' => 'datetime'];
    }

    public function items(): HasMany
    {
        return $this->hasMany(PartOrderItem::class);
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }
}
