<?php

declare(strict_types=1);

namespace App\Domain\Escrow;

use App\Domain\Money\Money;
use PHPUnit\Framework\TestCase;

final class LedgerBalanceTest extends TestCase
{
    public function test_an_empty_ledger_holds_nothing(): void
    {
        $balance = LedgerBalance::from([]);

        $this->assertSame(0, $balance->held->cents);
        $this->assertSame(0, $balance->available->cents);
        $this->assertSame(0, $balance->paidOut->cents);
    }

    public function test_a_hold_sits_in_escrow(): void
    {
        $balance = LedgerBalance::from([LedgerMovement::hold(Money::fromCents(9000))]);

        $this->assertSame(9000, $balance->held->cents);
        $this->assertSame(0, $balance->available->cents);
        $this->assertSame(0, $balance->paidOut->cents);
    }

    public function test_a_release_moves_the_hold_into_the_available_balance(): void
    {
        $balance = LedgerBalance::from([
            LedgerMovement::hold(Money::fromCents(9000)),
            LedgerMovement::release(Money::fromCents(9000)),
        ]);

        $this->assertSame(0, $balance->held->cents);
        $this->assertSame(9000, $balance->available->cents);
        $this->assertSame(0, $balance->paidOut->cents);
    }

    public function test_a_payout_empties_the_available_balance(): void
    {
        $balance = LedgerBalance::from([
            LedgerMovement::hold(Money::fromCents(9000)),
            LedgerMovement::release(Money::fromCents(9000)),
            LedgerMovement::payout(Money::fromCents(9000)),
        ]);

        $this->assertSame(0, $balance->held->cents);
        $this->assertSame(0, $balance->available->cents);
        $this->assertSame(9000, $balance->paidOut->cents);
    }

    public function test_it_keeps_an_undelivered_hold_apart_from_a_paid_out_release(): void
    {
        $balance = LedgerBalance::from([
            LedgerMovement::hold(Money::fromCents(9000)),
            LedgerMovement::release(Money::fromCents(9000)),
            LedgerMovement::payout(Money::fromCents(9000)),
            LedgerMovement::hold(Money::fromCents(4500)),
        ]);

        $this->assertSame(4500, $balance->held->cents);
        $this->assertSame(0, $balance->available->cents);
        $this->assertSame(9000, $balance->paidOut->cents);
    }

    public function test_a_balance_with_money_available_is_payable(): void
    {
        $balance = LedgerBalance::from([
            LedgerMovement::hold(Money::fromCents(9000)),
            LedgerMovement::release(Money::fromCents(9000)),
        ]);

        $this->assertTrue($balance->isPayable());
    }

    public function test_a_balance_still_in_escrow_is_not_payable(): void
    {
        $this->assertFalse(LedgerBalance::from([LedgerMovement::hold(Money::fromCents(9000))])->isPayable());
    }
}
