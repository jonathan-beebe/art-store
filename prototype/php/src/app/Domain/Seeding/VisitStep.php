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
 */
final readonly class VisitStep
{
    public function __construct(
        public StepKind $kind,
        public DateTimeImmutable $at,
        public ?int $listingSlot,
    ) {}
}
