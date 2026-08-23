<?php

declare(strict_types=1);

namespace App\Domain\Escrow;

use App\Domain\Money\Money;

it('holds nothing for an empty ledger', function (): void {
    $balance = LedgerBalance::from([]);

    expect($balance->held->cents)->toBe(0)
        ->and($balance->available->cents)->toBe(0)
        ->and($balance->paidOut->cents)->toBe(0);
});

it('sits a hold in escrow', function (): void {
    $balance = LedgerBalance::from([LedgerMovement::hold(Money::fromCents(9000))]);

    expect($balance->held->cents)->toBe(9000)
        ->and($balance->available->cents)->toBe(0)
        ->and($balance->paidOut->cents)->toBe(0);
});

it('moves a release from held into available', function (): void {
    $balance = LedgerBalance::from([
        LedgerMovement::hold(Money::fromCents(9000)),
        LedgerMovement::release(Money::fromCents(9000)),
    ]);

    expect($balance->held->cents)->toBe(0)
        ->and($balance->available->cents)->toBe(9000)
        ->and($balance->paidOut->cents)->toBe(0);
});

it('empties the available balance on payout', function (): void {
    $balance = LedgerBalance::from([
        LedgerMovement::hold(Money::fromCents(9000)),
        LedgerMovement::release(Money::fromCents(9000)),
        LedgerMovement::payout(Money::fromCents(9000)),
    ]);

    expect($balance->held->cents)->toBe(0)
        ->and($balance->available->cents)->toBe(0)
        ->and($balance->paidOut->cents)->toBe(9000);
});

it('keeps an undelivered hold apart from a paid-out release', function (): void {
    $balance = LedgerBalance::from([
        LedgerMovement::hold(Money::fromCents(9000)),
        LedgerMovement::release(Money::fromCents(9000)),
        LedgerMovement::payout(Money::fromCents(9000)),
        LedgerMovement::hold(Money::fromCents(4500)),
    ]);

    expect($balance->held->cents)->toBe(4500)
        ->and($balance->available->cents)->toBe(0)
        ->and($balance->paidOut->cents)->toBe(9000);
});

it('is payable with money available', function (): void {
    $balance = LedgerBalance::from([
        LedgerMovement::hold(Money::fromCents(9000)),
        LedgerMovement::release(Money::fromCents(9000)),
    ]);

    expect($balance->isPayable())->toBeTrue();
});

it('is not payable while still in escrow', function (): void {
    $balance = LedgerBalance::from([LedgerMovement::hold(Money::fromCents(9000))]);

    expect($balance->isPayable())->toBeFalse();
});
