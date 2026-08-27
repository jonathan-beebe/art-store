<?php

declare(strict_types=1);

namespace App\Domain\Configurator;

it('is unavailable once disabled, whatever else is true', function (): void {
    expect(VariantAvailability::resolve(enabled: false, isSerialized: false, availableUnitCount: 0, quantity: null)->available)->toBeFalse();
});

it('needs an available unit when serialized', function (): void {
    expect(VariantAvailability::resolve(enabled: true, isSerialized: true, availableUnitCount: 1, quantity: null)->available)->toBeTrue()
        ->and(VariantAvailability::resolve(enabled: true, isSerialized: true, availableUnitCount: 0, quantity: null)->available)->toBeFalse();
});

it('is available with no tracked quantity or a positive one', function (): void {
    expect(VariantAvailability::resolve(enabled: true, isSerialized: false, availableUnitCount: 0, quantity: null)->available)->toBeTrue()
        ->and(VariantAvailability::resolve(enabled: true, isSerialized: false, availableUnitCount: 0, quantity: 3)->available)->toBeTrue()
        ->and(VariantAvailability::resolve(enabled: true, isSerialized: false, availableUnitCount: 0, quantity: 0)->available)->toBeFalse();
});
