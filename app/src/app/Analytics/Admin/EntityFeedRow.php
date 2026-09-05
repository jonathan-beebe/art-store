<?php

declare(strict_types=1);

namespace App\Analytics\Admin;

use DateTimeImmutable;

/**
 * One row of an entity page's event feed: what happened, and the other
 * party. On a listing's or a store's page, the other party is the actor
 * that caused it. On an actor's page, it is whatever the event's own
 * subject names — a listing, an order, a cart, a store, or a help article,
 * `$otherKind` says which, and the page routes its link accordingly.
 * `$otherExists` is false for a listing or a store that no longer exists
 * and for a cart or a help article, neither of which carries a page of its
 * own to link to; an actor's or an order's row is never missing since
 * neither kind of row is ever deleted. `$listingTitles` is the listings an
 * order or cart subject spans, empty for a listing, a store, a help
 * article, or an actor subject, which already names its own listing,
 * store, article, or the actor as `$otherLabel`.
 */
final readonly class EntityFeedRow
{
    /**
     * @param  list<string>  $listingTitles
     */
    public function __construct(
        public string $name,
        public string $iconPath,
        public string $verb,
        public string $otherLabel,
        public string $otherId,
        public string $otherKind,
        public bool $otherExists,
        public array $listingTitles,
        public ?string $ip,
        public ?string $sessionId,
        public ?string $requestId,
        public DateTimeImmutable $occurredAt,
    ) {}
}
