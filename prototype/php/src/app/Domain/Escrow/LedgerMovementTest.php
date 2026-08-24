<?php

declare(strict_types=1);

namespace App\Domain\Escrow;

use App\Domain\Money\Money;

it('carries the net amount and type for each movement kind', function (
    LedgerMovement $movement,
    LedgerEntryType $expectedType,
    int $expectedCents,
): void {
    expect($movement->type)->toBe($expectedType)
        ->and($movement->amount->cents)->toBe($expectedCents);
})->with([
    'a hold carries the net into escrow' => [LedgerMovement::hold(Money::fromCents(9000)), LedgerEntryType::Held, 9000],
    'a release carries the net out of escrow' => [LedgerMovement::release(Money::fromCents(9000)), LedgerEntryType::Released, 9000],
    'a payout carries a negative amount' => [LedgerMovement::payout(Money::fromCents(9000)), LedgerEntryType::PaidOut, -9000],
    'a refund runs the net back out' => [LedgerMovement::refund(Money::fromCents(9000)), LedgerEntryType::Refunded, -9000],
]);

it('names the fulfillment a movement belongs to, so the fold can net a refund against its own sale', function (): void {
    $movement = LedgerMovement::of(LedgerEntryType::Refunded, Money::fromCents(-9000), 'ful_00000000000000000000000001');

    expect($movement->fulfillmentId)->toBe('ful_00000000000000000000000001');
});

it('leaves a payout without a fulfillment, because it settles a seller rather than a sale', function (): void {
    expect(LedgerMovement::payout(Money::fromCents(9000))->fulfillmentId)->toBeNull();
});
