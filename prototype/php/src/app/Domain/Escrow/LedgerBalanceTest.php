<?php

declare(strict_types=1);

namespace App\Domain\Escrow;

use App\Domain\Money\Money;

it('holds nothing for an empty ledger', function (): void {
    $balance = LedgerBalance::from([]);

    expect($balance->held->cents)->toBe(0)
        ->and($balance->available->cents)->toBe(0)
        ->and($balance->paidOut->cents)->toBe(0)
        ->and($balance->refunded->cents)->toBe(0);
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

it('takes a refund before release back out of escrow, releasing nothing', function (): void {
    $balance = LedgerBalance::from([
        LedgerMovement::of(LedgerEntryType::Held, Money::fromCents(9000), 'ful_1'),
        LedgerMovement::of(LedgerEntryType::Refunded, Money::fromCents(-9000), 'ful_1'),
    ]);

    expect($balance->held->cents)->toBe(0)
        ->and($balance->available->cents)->toBe(0)
        ->and($balance->paidOut->cents)->toBe(0);
});

it('drops the available balance on a refund after release', function (): void {
    $balance = LedgerBalance::from([
        LedgerMovement::of(LedgerEntryType::Held, Money::fromCents(9000), 'ful_1'),
        LedgerMovement::of(LedgerEntryType::Released, Money::fromCents(9000), 'ful_1'),
        LedgerMovement::of(LedgerEntryType::Refunded, Money::fromCents(-9000), 'ful_1'),
    ]);

    expect($balance->held->cents)->toBe(0)
        ->and($balance->available->cents)->toBe(0)
        ->and($balance->paidOut->cents)->toBe(0);
});

it('carries a negative available balance when the refund lands after the payout', function (): void {
    $balance = LedgerBalance::from([
        LedgerMovement::of(LedgerEntryType::Held, Money::fromCents(9000), 'ful_1'),
        LedgerMovement::of(LedgerEntryType::Released, Money::fromCents(9000), 'ful_1'),
        LedgerMovement::of(LedgerEntryType::PaidOut, Money::fromCents(-9000), null),
        LedgerMovement::of(LedgerEntryType::Refunded, Money::fromCents(-9000), 'ful_1'),
    ]);

    expect($balance->held->cents)->toBe(0)
        ->and($balance->available->cents)->toBe(-9000)
        ->and($balance->paidOut->cents)->toBe(9000);
});

it('nets a refund against its own sale rather than another still in escrow', function (): void {
    $balance = LedgerBalance::from([
        LedgerMovement::of(LedgerEntryType::Held, Money::fromCents(9000), 'ful_1'),
        LedgerMovement::of(LedgerEntryType::Released, Money::fromCents(9000), 'ful_1'),
        LedgerMovement::of(LedgerEntryType::Refunded, Money::fromCents(-9000), 'ful_1'),
        LedgerMovement::of(LedgerEntryType::Held, Money::fromCents(5000), 'ful_2'),
    ]);

    expect($balance->held->cents)->toBe(5000)
        ->and($balance->available->cents)->toBe(0);
});

it('is not payable while a refund leaves the seller in the red', function (): void {
    $balance = LedgerBalance::from([
        LedgerMovement::of(LedgerEntryType::Held, Money::fromCents(9000), 'ful_1'),
        LedgerMovement::of(LedgerEntryType::Released, Money::fromCents(9000), 'ful_1'),
        LedgerMovement::of(LedgerEntryType::PaidOut, Money::fromCents(-9000), null),
        LedgerMovement::of(LedgerEntryType::Refunded, Money::fromCents(-9000), 'ful_1'),
    ]);

    expect($balance->isPayable())->toBeFalse();
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

it('reads a refund as a positive amount refunded', function (): void {
    $balance = LedgerBalance::from([
        LedgerMovement::of(LedgerEntryType::Held, Money::fromCents(9000), 'ful_1'),
        LedgerMovement::of(LedgerEntryType::Refunded, Money::fromCents(-9000), 'ful_1'),
    ]);

    expect($balance->refunded->cents)->toBe(9000);
});

it('sums refunds across every fulfillment, whichever timing each one landed at', function (): void {
    $balance = LedgerBalance::from([
        LedgerMovement::of(LedgerEntryType::Held, Money::fromCents(9000), 'ful_1'),
        LedgerMovement::of(LedgerEntryType::Refunded, Money::fromCents(-9000), 'ful_1'),
        LedgerMovement::of(LedgerEntryType::Held, Money::fromCents(4500), 'ful_2'),
        LedgerMovement::of(LedgerEntryType::Released, Money::fromCents(4500), 'ful_2'),
        LedgerMovement::of(LedgerEntryType::Refunded, Money::fromCents(-4500), 'ful_2'),
    ]);

    expect($balance->refunded->cents)->toBe(13500);
});

it('carries no refunded amount when nothing was refunded', function (): void {
    $balance = LedgerBalance::from([LedgerMovement::hold(Money::fromCents(9000))]);

    expect($balance->refunded->cents)->toBe(0);
});
