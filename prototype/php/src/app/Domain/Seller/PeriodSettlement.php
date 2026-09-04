<?php

declare(strict_types=1);

namespace App\Domain\Seller;

use DateTimeImmutable;

/**
 * Whether a period's payout ran, read from the two facts an adapter already
 * has to hand rather than by re-deciding "payable" itself: whether the
 * period is still in progress, and whether a `payouts` row exists for it.
 */
final readonly class PeriodSettlement
{
    private function __construct(
        public PeriodPayoutStatus $status,
        public ?DateTimeImmutable $paidAt,
    ) {}

    public static function of(bool $isCurrent, bool $hasPayoutRow, ?DateTimeImmutable $paidAt): self
    {
        return match (true) {
            $isCurrent => new self(PeriodPayoutStatus::InProgress, null),
            ! $hasPayoutRow => new self(PeriodPayoutStatus::None, null),
            $paidAt === null => new self(PeriodPayoutStatus::Pending, null),
            default => new self(PeriodPayoutStatus::Paid, $paidAt),
        };
    }
}
