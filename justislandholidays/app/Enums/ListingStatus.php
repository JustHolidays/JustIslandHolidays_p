<?php

namespace App\Enums;

enum ListingStatus: string
{
    case Draft = 'draft';
    case PendingReview = 'pending_review';
    case Live = 'live';
    case Paused = 'paused';
    case Rejected = 'rejected';
    case Archived = 'archived';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::PendingReview => 'Waiting for review',
            self::Live => 'Live',
            self::Paused => 'Paused',
            self::Rejected => 'Rejected',
            self::Archived => 'Archived',
        };
    }
}
