<?php

declare(strict_types=1);

namespace App\Domain\Configurator;

use App\Domain\Money\Money;
use InvalidArgumentException;
use LogicException;

it('refuses a tier below quantity 2', function (): void {
    QuantityDiscount::of(1, 500);
})->throws(InvalidArgumentException::class);

it('refuses a discount outside 1 to 9999 basis points', function (int $bps): void {
    QuantityDiscount::of(10, $bps);
})->with([0, 10_000])->throws(InvalidArgumentException::class);

it('picks the highest tier the quantity clears', function (): void {
    $breaks = [QuantityDiscount::of(50, 500), QuantityDiscount::of(100, 1000), QuantityDiscount::of(200, 1500)];

    $bestFor = fn (int $quantity): QuantityDiscount => QuantityDiscount::bestFor($breaks, $quantity)
        ?? throw new LogicException('Expected a tier.');

    expect(QuantityDiscount::bestFor($breaks, 40))->toBeNull()
        ->and($bestFor(50)->minQty)->toBe(50)
        ->and($bestFor(150)->minQty)->toBe(100)
        ->and($bestFor(500)->minQty)->toBe(200);
});

it('discounts a subtotal by its basis points, rounded half away from zero', function (): void {
    expect(QuantityDiscount::of(100, 1000)->discountFor(Money::fromCents(10_000))->cents)->toBe(1000)
        ->and(QuantityDiscount::of(2, 1)->discountFor(Money::fromCents(50_050))->cents)->toBe(5);
});
