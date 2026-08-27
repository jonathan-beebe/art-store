<?php

declare(strict_types=1);

namespace App\Support\Configurator;

use App\Domain\Configurator\PriceBreakdown;

it('carries the resolved configurator state for a render', function (): void {
    $configuration = new ListingConfiguration(
        hasConfigurator: true,
        hasVariants: true,
        axes: [],
        selectedOptionValueIdsByAxis: [],
        variantId: 'vrt_1',
        isSerialized: false,
        units: [],
        selectedUnitId: null,
        modifiers: [],
        quantity: 2,
        quantityTiers: [],
        breakdown: PriceBreakdown::of([]),
        canAddToCart: true,
        unavailableReason: null,
        configurationSnapshot: [],
        answersSnapshot: [],
        fingerprintAnswers: [],
    );

    expect($configuration->hasConfigurator)->toBeTrue()
        ->and($configuration->variantId)->toBe('vrt_1')
        ->and($configuration->quantity)->toBe(2)
        ->and($configuration->canAddToCart)->toBeTrue();
});
