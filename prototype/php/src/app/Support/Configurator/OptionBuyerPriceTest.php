<?php

declare(strict_types=1);

namespace App\Support\Configurator;

use App\Domain\Money\Money;

it('charges exactly the listing price for a price-neutral option', function (): void {
    expect(OptionBuyerPrice::forOption(Money::fromCents(2400), Money::zero())->cents)->toBe(2400);
});

it('adds a positive price difference to the listing price', function (): void {
    expect(OptionBuyerPrice::forOption(Money::fromCents(2400), Money::fromCents(600))->cents)->toBe(3000);
});

it('adds a negative price difference to the listing price', function (): void {
    expect(OptionBuyerPrice::forOption(Money::fromCents(2400), Money::fromCents(-500))->cents)->toBe(1900);
});
