<?php

declare(strict_types=1);

namespace App\Domain\Listings;

/**
 * The pricing shape a seller picks on the create screen (DSGN-003): a plain
 * item at one price, an item that comes in versions each priced on its own,
 * or an item at one price with extras that add to it. The shape only routes
 * the create flow to the fields it needs — `OneThing` and `Extras` land on a
 * plain listing, `Versions` and (optionally) `Extras` add an option axis —
 * the configurator draws no line between how a listing was created and how
 * it grows afterwards. Never rendered seller-facing by these words; the
 * create screen's own copy carries the seller's language.
 */
enum ListingCreationShape: string
{
    case OneThing = 'one';
    case Versions = 'versions';
    case Extras = 'extras';
}
