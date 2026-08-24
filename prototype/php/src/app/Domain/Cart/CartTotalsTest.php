<?php

declare(strict_types=1);

namespace App\Domain\Cart;

use App\Domain\Money\Money;
use DomainException;

it('totals nothing for an empty cart', function (): void {
    $totals = CartTotals::from([]);

    expect($totals->itemCount)->toBe(0)
        ->and($totals->subtotal->cents)->toBe(0)
        ->and($totals->subtotalsBySeller())->toBe([]);
});

it('counts every item across the lines', function (): void {
    $totals = CartTotals::from([
        CartLine::of('sel_00000000000000000000000001', Money::fromCents(4500), 2),
        CartLine::of('sel_00000000000000000000000002', Money::fromCents(1000), 1),
    ]);

    expect($totals->itemCount)->toBe(3);
});

it('adds the line totals into a subtotal', function (): void {
    $totals = CartTotals::from([
        CartLine::of('sel_00000000000000000000000001', Money::fromCents(4500), 2),
        CartLine::of('sel_00000000000000000000000002', Money::fromCents(1000), 1),
    ]);

    expect($totals->subtotal->cents)->toBe(10000);
});

it('groups the subtotal by seller', function (): void {
    $totals = CartTotals::from([
        CartLine::of('sel_00000000000000000000000002', Money::fromCents(1000), 1),
        CartLine::of('sel_00000000000000000000000001', Money::fromCents(4500), 2),
        CartLine::of('sel_00000000000000000000000002', Money::fromCents(2500), 1),
    ]);

    expect(array_map(
        fn (Money $subtotal): int => $subtotal->cents,
        $totals->subtotalsBySeller(),
    ))->toBe(['sel_00000000000000000000000001' => 9000, 'sel_00000000000000000000000002' => 3500]);
});

it('needs at least one line for checkout', function (): void {
    expect(fn () => CartTotals::forCheckout([]))->toThrow(DomainException::class);
});

it('totals the lines it is given for checkout', function (): void {
    $totals = CartTotals::forCheckout([CartLine::of('sel_00000000000000000000000001', Money::fromCents(4500), 2)]);

    expect($totals->subtotal->cents)->toBe(9000);
});
