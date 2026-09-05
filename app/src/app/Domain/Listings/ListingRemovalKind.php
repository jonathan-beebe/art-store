<?php

declare(strict_types=1);

namespace App\Domain\Listings;

/**
 * How long a removal stands. A temporary removal is a hold pending review
 * and lifts back to whatever status the listing already carried. A
 * permanent one never lifts. Raising a temporary removal to permanent
 * means lifting it and removing again, so the seller reads one status at
 * a time.
 */
enum ListingRemovalKind: string
{
    case Temporary = 'temporary';
    case Permanent = 'permanent';

    public function label(): string
    {
        return match ($this) {
            self::Temporary => 'Temporary',
            self::Permanent => 'Permanent',
        };
    }

    public function canLift(): bool
    {
        return $this === self::Temporary;
    }
}
