<?php

declare(strict_types=1);

use App\Domain\Configurator\PricingMode;
use App\Domain\Configurator\StandaloneOptionPriceGuard;
use App\Domain\DomainRuleViolation;

it('refuses a standalone option with no price', function (): void {
    expect(fn () => StandaloneOptionPriceGuard::forOption(PricingMode::Standalone, null))
        ->toThrow(DomainRuleViolation::class, 'Every option on this choice needs its own price.');
});

it('admits a standalone option with a price and any add-on option', function (): void {
    StandaloneOptionPriceGuard::forOption(PricingMode::Standalone, 0);
    StandaloneOptionPriceGuard::forOption(PricingMode::AddOn, null);
    StandaloneOptionPriceGuard::forOption(PricingMode::AddOn, 500);

    expect(true)->toBeTrue();
});
