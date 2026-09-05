<?php

declare(strict_types=1);

namespace App\Support\Configurator;

use App\Domain\Configurator\PricingMode;
use App\Domain\Money\Money;
use App\Models\OptionValue;

it('charges exactly the listing price for a price-neutral add-on option', function (): void {
    $value = new OptionValue(['surcharge_cents' => 0]);

    expect(OptionBuyerPrice::forOption(Money::fromCents(2400), PricingMode::AddOn, $value)->cents)->toBe(2400);
});

it('adds a positive price difference to the listing price', function (): void {
    $value = new OptionValue(['surcharge_cents' => 600]);

    expect(OptionBuyerPrice::forOption(Money::fromCents(2400), PricingMode::AddOn, $value)->cents)->toBe(3000);
});

it('adds a negative price difference to the listing price', function (): void {
    $value = new OptionValue(['surcharge_cents' => -500]);

    expect(OptionBuyerPrice::forOption(Money::fromCents(2400), PricingMode::AddOn, $value)->cents)->toBe(1900);
});

it('charges a standalone option its own price, ignoring the listing price', function (): void {
    $value = new OptionValue(['price_cents' => 1800]);

    expect(OptionBuyerPrice::forOption(Money::fromCents(2400), PricingMode::Standalone, $value)->cents)->toBe(1800);
});
