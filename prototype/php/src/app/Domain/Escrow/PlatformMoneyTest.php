<?php

declare(strict_types=1);

namespace App\Domain\Escrow;

use App\Domain\Money\Money;
use App\Domain\Orders\FulfillmentStatus;

it('carries the ledger balance and the fees side by side', function (): void {
    $balance = LedgerBalance::from([
        LedgerMovement::hold(Money::fromCents(9000)),
        LedgerMovement::release(Money::fromCents(9000)),
    ]);
    $fees = PlatformFees::from([['status' => FulfillmentStatus::Delivered, 'feeCents' => 900]]);

    $money = PlatformMoney::of($balance, $fees);

    expect($money->held->cents)->toBe(0)
        ->and($money->available->cents)->toBe(9000)
        ->and($money->paidOut->cents)->toBe(0)
        ->and($money->refunded->cents)->toBe(0)
        ->and($money->feesEarned->cents)->toBe(900)
        ->and($money->feesRefunded->cents)->toBe(0);
});
