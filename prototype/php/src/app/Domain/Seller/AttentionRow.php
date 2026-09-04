<?php

declare(strict_types=1);

namespace App\Domain\Seller;

/**
 * One line under a focus group on the dashboard: the thing that wants
 * doing, and the link that opens it. `$urgent` marks a row the group
 * renders in red — a parcel that has been waiting past
 * {@see AttentionQueue::SHIP_OVERDUE_DAYS}.
 */
final readonly class AttentionRow
{
    public function __construct(
        public string $initials,
        public string $title,
        public string $supporting,
        public string $meta,
        public string $href,
        public bool $urgent = false,
    ) {}
}
