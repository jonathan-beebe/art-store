<?php

declare(strict_types=1);

namespace App\Domain\Configurator;

it('carries the facts ConfiguratorPublishValidation judges', function (): void {
    $snapshot = new VariantSnapshot(
        id: 'vrt_01',
        enabled: true,
        priceCents: 1500,
        isSerialized: false,
        availableUnitCount: 0,
        axisIdsCovered: ['axs_01', 'axs_02'],
    );

    expect($snapshot->id)->toBe('vrt_01')
        ->and($snapshot->enabled)->toBeTrue()
        ->and($snapshot->priceCents)->toBe(1500)
        ->and($snapshot->isSerialized)->toBeFalse()
        ->and($snapshot->availableUnitCount)->toBe(0)
        ->and($snapshot->axisIdsCovered)->toBe(['axs_01', 'axs_02']);
});
