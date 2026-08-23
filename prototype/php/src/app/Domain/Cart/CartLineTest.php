<?php

declare(strict_types=1);

namespace App\Domain\Cart;

use App\Domain\Money\Money;
use InvalidArgumentException;

it('totals its unit price across the quantity', function (): void {
    $line = CartLine::of(1, Money::fromCents(4500), 3);

    expect($line->total()->cents)->toBe(13500);
});

it('needs at least one item', function (): void {
    expect(fn () => CartLine::of(1, Money::fromCents(4500), 0))
        ->toThrow(InvalidArgumentException::class);
});
