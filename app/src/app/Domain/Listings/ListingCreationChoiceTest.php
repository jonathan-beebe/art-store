<?php

declare(strict_types=1);

use App\Domain\Configurator\PricingMode;
use App\Domain\Listings\ListingCreationChoice;

it('prices a versions row as the option\'s own price', function (): void {
    $choice = ListingCreationChoice::versions('Size', [['label' => '8x10', 'cents' => 1800]]);

    expect($choice->pricingMode)->toBe(PricingMode::Standalone)
        ->and($choice->priceCentsOf(1800))->toBe(1800);
});

it('leaves an extras row with no own price, since its cents are a surcharge', function (): void {
    $choice = ListingCreationChoice::extras('Finish', [['label' => 'Carved handle', 'cents' => 1400]]);

    expect($choice->pricingMode)->toBe(PricingMode::AddOn)
        ->and($choice->priceCentsOf(1400))->toBeNull();
});
