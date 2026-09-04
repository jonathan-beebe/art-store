<?php

declare(strict_types=1);

namespace App\Domain\Seller;

use App\Domain\Analytics\RangeChange;
use App\Domain\Escrow\PayoutPeriod;
use App\Domain\Money\Money;

/**
 * A payout period's business, folded from facts an adapter reads off the
 * database: how many orders were placed, what they totalled gross before
 * and after the platform fee, and what was refunded — dated by when the
 * refund happened, which can be a later period than the sale itself. Gross
 * sales and fees carry every order placed that period, live or since
 * refunded; a refund nets itself back out through the `refunds` figure of
 * whichever period it lands in.
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
     * How this period's sales compare with `$previous`'s.
     */
    public function salesChange(self $previous): RangeChange
    {
        return RangeChange::between($this->sales->cents, $previous->sales->cents);
    }

    /**
     * @param  list<SaleFact>  $sales
     * @param  list<RefundFact>  $refunds
     */
    private static function forPeriod(PayoutPeriod $period, array $sales, array $refunds): self
    {
        return new self(
            $period,
            count($sales),
            array_reduce($sales, fn (Money $sum, SaleFact $sale): Money => $sum->add($sale->subtotal), Money::zero()),
            array_reduce($sales, fn (Money $sum, SaleFact $sale): Money => $sum->add($sale->fee), Money::zero()),
            array_reduce($refunds, fn (Money $sum, RefundFact $refund): Money => $sum->add($refund->amount), Money::zero()),
        );
    }
}
