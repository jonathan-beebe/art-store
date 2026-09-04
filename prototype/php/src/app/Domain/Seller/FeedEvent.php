<?php

declare(strict_types=1);

namespace App\Domain\Seller;

use DateTimeImmutable;

/**
 * One row of an activity feed: when it happened, who did it, what happened,
 * and — for a message or a decline — the words themselves.
 */
final readonly class FeedEvent
{
    public function __construct(
        public DateTimeImmutable $occurredAt,
        public ActivityKind $kind,
        public FeedIcon $icon,
        public string $actor,
        public string $text,
        public ?string $quote = null,
        public ?string $link = null,
    ) {}

    public function isOf(ActivityKind $kind): bool
    {
        return $this->kind === $kind;
    }
}
