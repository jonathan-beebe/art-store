<?php

declare(strict_types=1);

namespace App\Analytics\Admin;

use DateTimeImmutable;

/**
 * One row of an entity page's event feed: what happened, and the other
 * party — on a listing's page, the actor that caused it; on an actor's
 * page, the listing it happened to. `$otherKind` names which one `$otherId`
 * is, for the page to route its link; `$otherExists` is false only for a
 * listing that no longer exists — an actor's row is never missing since
 * customer rows are never deleted.
 */
final readonly class EntityFeedRow
{
    public function __construct(
        public string $name,
        public string $iconPath,
        public string $verb,
        public string $otherLabel,
        public string $otherId,
        public string $otherKind,
        public bool $otherExists,
        public ?string $ip,
        public ?string $sessionId,
        public ?string $requestId,
        public DateTimeImmutable $occurredAt,
    ) {}
}
