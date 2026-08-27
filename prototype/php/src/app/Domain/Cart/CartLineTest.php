<?php

declare(strict_types=1);

namespace App\Domain\Cart;

use App\Domain\Money\Money;
use InvalidArgumentException;

it('totals its unit price across the quantity', function (): void {
    $line = CartLine::of('sel_00000000000000000000000001', Money::fromCents(4500), 3);

    expect($line->total()->cents)->toBe(13500);
});

it('needs at least one item', function (): void {
    expect(fn () => CartLine::of('sel_00000000000000000000000001', Money::fromCents(4500), 0))
        ->toThrow(InvalidArgumentException::class);
});

it('totals to a precomputed breakdown total instead of unit price times quantity', function (): void {
    $line = CartLine::ofBreakdownTotal('sel_00000000000000000000000001', Money::fromCents(4500), 3, Money::fromCents(12000));

    expect($line->total()->cents)->toBe(12000);
});

it('needs at least one item for a precomputed total too', function (): void {
    expect(fn () => CartLine::ofBreakdownTotal('sel_00000000000000000000000001', Money::fromCents(4500), 0, Money::fromCents(12000)))
        ->toThrow(InvalidArgumentException::class);
});
