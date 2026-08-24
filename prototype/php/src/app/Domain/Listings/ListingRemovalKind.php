<?php

declare(strict_types=1);

namespace App\Domain\Listings;

/**
 * How long a removal stands. A temporary removal is a hold pending review and
 * lifts back to whatever status the listing already carried; a permanent one
 * never lifts, so raising a temporary removal to permanent is done by lifting
 * it and removing again — one reason for the seller to read rather than two
 * standing at once.
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
