<?php

declare(strict_types=1);

namespace App\Domain\Seller;

use App\Domain\Escrow\PayoutPeriod;
use App\Domain\Money\Money;

/**
 * A payout period's business, folded from facts an adapter reads off the
 * database: how many orders were placed, what the live ones totalled before
 * and after the platform fee, and what was refunded — regardless of which
 * period the refunded sale itself was placed in.
 */
final readonly class PeriodFigures
{
    private function __construct(
        public PayoutPeriod $period,
        public int $orderCount,
        public Money $sales,
        public Money $fees,
        public Money $refunds,
    ) {}

    public function net(): Money
    {
        return $this->sales->subtract($this->fees)->subtract($this->refunds);
    }

    /**
     * Buckets every fact into whichever of the given periods it falls in.
     * A fact outside every period is silently dropped, the way a caller
     * that names the exact window it wants intends.
     *
     * @param  list<PayoutPeriod>  $periods
     * @param  list<SaleFact>  $sales
     * @param  list<RefundFact>  $refunds
     * @return list<self>
     */
    public static function bucket(array $periods, array $sales, array $refunds): array
    {
        return array_map(
            fn (PayoutPeriod $period): self => self::forPeriod(
                $period,
                array_values(array_filter($sales, fn (SaleFact $sale): bool => $period->contains($sale->placedAt))),
                array_values(array_filter($refunds, fn (RefundFact $refund): bool => $period->contains($refund->occurredAt))),
            ),
            $periods,
        );
    }

    /**
     * @param  list<SaleFact>  $sales
     * @param  list<RefundFact>  $refunds
     */
    private static function forPeriod(PayoutPeriod $period, array $sales, array $refunds): self
    {
        $live = array_values(array_filter($sales, fn (SaleFact $sale): bool => $sale->isLive));

        return new self(
            $period,
            count($sales),
            array_reduce($live, fn (Money $sum, SaleFact $sale): Money => $sum->add($sale->subtotal), Money::zero()),
            array_reduce($live, fn (Money $sum, SaleFact $sale): Money => $sum->add($sale->fee), Money::zero()),
            array_reduce($refunds, fn (Money $sum, RefundFact $refund): Money => $sum->add($refund->amount), Money::zero()),
        );
    }
}
