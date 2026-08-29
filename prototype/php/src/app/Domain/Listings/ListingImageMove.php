<?php

declare(strict_types=1);

namespace App\Domain\Listings;

/**
 * Which neighbor a listing image trades places with — the whole of
 * "reorder" offered to a seller with no drag-and-drop, JavaScript off.
 */
enum ListingImageMove: string
{
    case Up = 'up';
    case Down = 'down';
}
