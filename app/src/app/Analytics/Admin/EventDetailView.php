<?php

declare(strict_types=1);

namespace App\Analytics\Admin;

use App\Domain\Analytics\EventBreakdown;

/**
 * The event page's whole read: one event name's range tiles, its daily
 * series, and its breakdown rows — {@see EventDetail::forRange()}'s
 * result.
 */
final readonly class EventDetailView
{
    /**
     * @param  list<EventTile>  $tiles
     * @param  list<int>  $daily  one count per day of the range, oldest first
     * @param  list<EventBreakdownRow>  $rows  ordered by current desc
     */
    public function __construct(
        public string $name,
        public string $label,
        public array $tiles,
        public array $daily,
        public string $firstDay,
        public string $lastDay,
        public EventBreakdown $breakdown,
        public array $rows,
    ) {}
}
