<?php

declare(strict_types=1);

use App\Domain\Configurator\PricingMode;

it('answers whether an axis prices its options on their own', function (): void {
    expect(PricingMode::Standalone->isStandalone())->toBeTrue()
        ->and(PricingMode::AddOn->isStandalone())->toBeFalse();
});
