<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RentalReservation extends Model
{
    protected $fillable = ['listing_id', 'user_id', 'source', 'status', 'starts_at', 'ends_at', 'expires_at', 'snapshot', 'private_note', 'client_request_id', 'request_hash'];

    protected $hidden = ['private_note', 'request_hash'];

    protected function casts(): array
    {
        return ['starts_at' => 'datetime', 'ends_at' => 'datetime', 'expires_at' => 'datetime', 'snapshot' => 'array'];
    }

    public function listing(): BelongsTo
    {
        return $this->belongsTo(Listing::class);
    }
}
