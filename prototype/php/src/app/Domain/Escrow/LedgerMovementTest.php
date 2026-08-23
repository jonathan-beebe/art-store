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
]);
