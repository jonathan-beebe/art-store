<?php

namespace App\Domain\Escrow;

use App\Domain\Money\Money;
use PHPUnit\Framework\TestCase;

final class LedgerMovementTest extends TestCase
{
    public function test_a_hold_carries_the_net_into_escrow(): void
    {
        $movement = LedgerMovement::hold(Money::fromCents(9000));

        $this->assertSame(LedgerEntryType::Held, $movement->type);
        $this->assertSame(9000, $movement->amount->cents);
    }

    public function test_a_release_carries_the_net_out_of_escrow(): void
    {
        $movement = LedgerMovement::release(Money::fromCents(9000));

        $this->assertSame(LedgerEntryType::Released, $movement->type);
        $this->assertSame(9000, $movement->amount->cents);
    }

    public function test_a_payout_carries_a_negative_amount(): void
    {
        $movement = LedgerMovement::payout(Money::fromCents(9000));

        $this->assertSame(LedgerEntryType::PaidOut, $movement->type);
        $this->assertSame(-9000, $movement->amount->cents);
    }
}
