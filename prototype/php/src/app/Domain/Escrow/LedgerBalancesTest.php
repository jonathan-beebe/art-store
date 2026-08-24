<?php

declare(strict_types=1);

namespace App\Domain\Escrow;

use App\Domain\Money\Money;

it('folds each seller\'s movements into their own balance', function (): void {
    $balances = LedgerBalances::from([
        'sel_one' => [LedgerMovement::hold(Money::fromCents(9000)), LedgerMovement::release(Money::fromCents(4000))],
        'sel_two' => [LedgerMovement::hold(Money::fromCents(2500))],
    ]);

    expect($balances->of('sel_one')->held)->toBeMoney(5000)
        ->and($balances->of('sel_one')->available)->toBeMoney(4000)
        ->and($balances->of('sel_two')->held)->toBeMoney(2500)
        ->and($balances->of('sel_two')->available)->toBeMoney(0);
});

it('answers zero for a seller with no movements at all', function (): void {
    $balances = LedgerBalances::from([]);

    expect($balances->of('sel_unknown')->held)->toBeMoney(0)
        ->and($balances->of('sel_unknown')->available)->toBeMoney(0)
        ->and($balances->of('sel_unknown')->paidOut)->toBeMoney(0);
});
