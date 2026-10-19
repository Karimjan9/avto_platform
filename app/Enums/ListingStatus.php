<?php

namespace App\Enums;

enum ListingStatus: string
{
    case Draft = 'draft';
    case Pending = 'pending';
    case Published = 'published';
    case Rejected = 'rejected';
    case Sold = 'sold';
    case Archived = 'archived';

    public function allowsOwnerTransition(self $next): bool
    {
        return in_array($next, match ($this) {
            self::Draft => [self::Pending, self::Archived],
            self::Pending => [self::Draft, self::Archived],
            self::Published => [self::Draft, self::Sold, self::Archived],
            self::Rejected => [self::Draft, self::Pending, self::Archived],
            self::Sold => [self::Draft, self::Archived],
            self::Archived => [self::Draft, self::Pending],
        }, true);
    }
}
