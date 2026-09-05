<?php

declare(strict_types=1);

namespace App\Domain\Seller;

use App\Domain\Escrow\LedgerBalance;
use App\Domain\Escrow\PayoutPeriod;
use App\Domain\Money\Money;
use DateTimeImmutable;

/**
 * What the next weekly payout run pays a seller: the balance already
 * released and awaiting payout, negative when a past refund outran what
 * escrow could cover, and the date the run lands on. The current period's
 * payout runs the Monday after it ends, whichever week that is — a period
 * still in progress pays out on its own coming Monday, not the one just
 * passed.
 */
final readonly class PayoutEstimate
{
    private const int DAYS_IN_A_WEEK = 7;

    private function __construct(
        public Money $amount,
        public DateTimeImmutable $payoutDate,
        public int $releasedOrderCount,
    ) {}

    public static function from(LedgerBalance $balance, PayoutPeriod $currentPeriod, int $releasedOrderCount): self
    {
        return new self(
            $balance->available,
            $currentPeriod->start->modify('+'.self::DAYS_IN_A_WEEK.' days'),
            $releasedOrderCount,
        );
    }

    public function isCarryingNegative(): bool
    {
        return ! $this->amount->isPositive() && ! $this->amount->isZero();
    }
}
