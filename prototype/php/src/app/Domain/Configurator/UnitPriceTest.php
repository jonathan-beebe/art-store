<?php

declare(strict_types=1);

namespace App\Domain\Configurator;

use App\Domain\Money\Money;

it('uses the unit override before anything else', function (): void {
    $price = UnitPrice::resolve(Money::fromCents(3500), Money::fromCents(9900), Money::fromCents(2000), [Money::fromCents(500)]);

    expect($price->cents)->toBe(3500);
});

it('falls back to the variant override with no unit override', function (): void {
    $price = UnitPrice::resolve(null, Money::fromCents(9900), Money::fromCents(2000), [Money::fromCents(500)]);

    expect($price->cents)->toBe(9900);
});

it('falls back to base plus surcharges with no override at all', function (): void {
    $price = UnitPrice::resolve(null, null, Money::fromCents(2000), [Money::fromCents(500), Money::fromCents(150)]);

    expect($price->cents)->toBe(2650);
});
