<?php

namespace App\Services;

use Illuminate\Support\Collection;

class TrustBadges
{
    public static function has(Collection $records, string $kind, ?string $value = null): bool
    {
        return $records->contains(fn ($record) => $record->kind === $kind && $record->status === 'verified' && ($record->expires_at === null || $record->expires_at->isFuture()) && ($kind !== 'phone' || ($value !== null && $record->verified_value === hash('sha256', $value))));
    }
}
