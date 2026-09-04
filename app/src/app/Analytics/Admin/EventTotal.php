<?php

declare(strict_types=1);

namespace App\Analytics\Admin;

use App\Domain\Analytics\RangeChange;

/**
 * One event name's row on the admin analytics entry page: its count for
 * the chosen range, the count for the range before, and how many days,
 * subjects, and actors carried it. {@see EventTotals::forRange()} builds
 * one of these per {@see \App\Domain\Analytics\AnalyticsEventName} case
 * plus one for `page.view` — a roll-up with no subjects or actors of its
 * own.
 */
final readonly class EventTotal
{
    /**
     * @param  list<int>  $daily  one count per day of the range, oldest first
     */
    public function __construct(
        public string $name,
        public string $label,
        public int $current,
        public int $previous,
        public RangeChange $change,
        public array $daily,
        public ?int $subjects,
        public ?int $actors,
    ) {}
}
