<?php

declare(strict_types=1);

namespace App\Analytics\Admin;

use App\Domain\Analytics\PageViewSite;
use App\Domain\Analytics\RangeChange;

/**
 * One row of the event page's breakdown table: a listing, an actor, or —
 * for `page.view` — a route pattern, with its share of the event's current
 * total. `actorKind` carries a value only on a by-actor row; `site` only on
 * a by-pattern row — the view reads whichever one the page's `breakdown`
 * says this table holds.
 */
final readonly class EventBreakdownRow
{
    public function __construct(
        public string $id,
        public string $title,
        public ?string $actorKind,
        public ?PageViewSite $site,
        public int $current,
        public int $previous,
        public RangeChange $change,
        public string $sharePercent,
        public int $shareWidth,
    ) {}
}
