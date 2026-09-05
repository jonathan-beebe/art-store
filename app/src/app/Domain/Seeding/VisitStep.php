<?php

declare(strict_types=1);

namespace App\Domain\Seeding;

use DateTimeImmutable;

/**
 * One moment in a seeded session's script: what happened, when, and — for a
 * step that acts on a specific listing — which one, as an index into
 * whatever pool of listings `App\Console\Commands\SeedActivity` resolved
 * before driving the plan. A step naming no listing (`CheckoutOpen`,
 * `OrderPlace`, `OrderPay`, `OrderCancel`, and a `SupportQuestion` that
 * asks about nothing in particular) leaves `listingSlot` null.
 *
 * `ip` and `path` exist for the two bad-actor session kinds only, and stay
 * null for every ordinary step. A scraper's step carries `ip`, since it
 * rotates addresses within one session that an ordinary visitor never
 * does; `SeedActivity` resolves its `listingSlot` against the live
 * catalog, since the catalog a scraper reaches by the third month holds
 * more listings than the pool a plan sizes itself from. A prober's
 * `ProbeRequest` step carries `path` in place of a listing — the one it
 * probed — and may carry `ip` too, since a prober rotates addresses the
 * same way a scraper does.
 */
final readonly class VisitStep
{
    public function __construct(
        public StepKind $kind,
        public DateTimeImmutable $at,
        public ?int $listingSlot,
        public ?string $ip = null,
        public ?string $path = null,
    ) {}
}
