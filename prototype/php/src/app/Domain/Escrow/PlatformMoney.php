<?php

declare(strict_types=1);

namespace App\Domain\Escrow;

use App\Domain\Money\Money;

/**
 * The whole platform's money in one place: escrow across every seller
 * ({@see LedgerBalances::total()}) beside what the platform kept and gave
 * back ({@see PlatformFees}). `/admin` and `/admin/accounting` show the same
 * six figures.
 */
final readonly class PlatformMoney
{
    private function __construct(
        public Money $held,
        public Money $available,
        public Money $paidOut,
        public Money $refunded,
        public Money $feesEarned,
        public Money $feesRefunded,
    ) {}

    public static function of(LedgerBalance $balance, PlatformFees $fees): self
    {
        return new self(
            $balance->held,
            $balance->available,
            $balance->paidOut,
            $balance->refunded,
            $fees->earned,
            $fees->refunded,
        );
    }
}
