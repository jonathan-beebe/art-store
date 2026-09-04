<?php

declare(strict_types=1);

namespace App\Domain\Seller;

/**
 * Every source's rows in one list, newest first. Two rows carrying the same
 * instant keep the order their sources were passed in, so a page reading the
 * same feed twice reads it the same way.
 */
final readonly class ActivityFeed
{
    /**
     * @param  list<FeedEvent>  $events
     */
    private function __construct(public array $events) {}

    /**
     * @param  list<FeedEvent>  ...$sources
     */
    public static function merge(array ...$sources): self
    {
        $events = array_merge([], ...$sources);

        // PHP's sort is stable, so equal instants come out in the order the
        // sources were merged.
        usort($events, fn (FeedEvent $left, FeedEvent $right): int => $right->occurredAt <=> $left->occurredAt);

        return new self($events);
    }

    /**
     * The rows of one kind. A null kind is the whole feed, which is what an
     * absent filter reads as.
     */
    public function filter(?ActivityKind $kind): self
    {
        if (! $kind instanceof ActivityKind) {
            return $this;
        }

        return new self(array_values(array_filter(
            $this->events,
            fn (FeedEvent $event): bool => $event->isOf($kind),
        )));
    }

    public function isEmpty(): bool
    {
        return $this->events === [];
    }
}
