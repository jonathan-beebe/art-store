<?php

declare(strict_types=1);

namespace App\Analytics\Admin;

use App\Domain\Analytics\FeedOtherKind;
use DateTimeImmutable;

/**
 * One row of an entity page's event feed: what happened, and the other
 * party. `$otherKind` names the other party ({@see FeedOtherKind}) and the
 * page routes its link accordingly. `$listingTitles` is the listings an
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
        public FeedOtherKind $otherKind,
        public bool $otherExists,
        public array $listingTitles,
        public ?string $ip,
        public ?string $sessionId,
        public ?string $requestId,
        public DateTimeImmutable $occurredAt,
    ) {}
}
