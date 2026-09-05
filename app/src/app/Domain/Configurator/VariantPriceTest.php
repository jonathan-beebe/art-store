<?php

declare(strict_types=1);

namespace App\Domain\Configurator;

use App\Domain\Money\Money;

it('sums the base price and every surcharge when there is no override', function (): void {
    $price = VariantPrice::resolve(
        Money::fromCents(2000),
        null,
        [Money::fromCents(500), Money::fromCents(150)],
    );

    expect($price->amount->cents)->toBe(2650);
});

it('uses the override instead of base plus surcharges when one is set', function (): void {
    $price = VariantPrice::resolve(
        Money::fromCents(2000),
        Money::fromCents(9900),
        [Money::fromCents(500)],
    );

    expect($price->amount->cents)->toBe(9900);
});

it('is just the base price with no surcharges', function (): void {
    expect(VariantPrice::resolve(Money::fromCents(1000), null, [])->amount->cents)->toBe(1000);
});

it('sums multiple standalone prices as the replacement base, ignoring the listing base price', function (): void {
    $price = VariantPrice::resolve(
        Money::fromCents(9999),
        null,
        [],
        [Money::fromCents(1200), Money::fromCents(800)],
    );

    expect($price->amount->cents)->toBe(2000);
});

it('adds surcharges on top of the summed standalone prices', function (): void {
    $price = VariantPrice::resolve(
        Money::fromCents(9999),
        null,
        [Money::fromCents(500), Money::fromCents(150)],
        [Money::fromCents(1200), Money::fromCents(800)],
    );

    expect($price->amount->cents)->toBe(2650);
});
