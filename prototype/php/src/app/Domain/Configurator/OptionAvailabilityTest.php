<?php

declare(strict_types=1);

namespace App\Domain\Configurator;

it('is not offered when no variant covers the combination', function (): void {
    $availability = OptionAvailability::resolve('combo-a', [], []);

    expect($availability->selectable)->toBeFalse()
        ->and($availability->reason)->toBe('not offered');
});

it('is not offered when the variant that covers it is disabled', function (): void {
    $availability = OptionAvailability::resolve('combo-a', ['combo-a' => false], ['combo-a' => true]);

    expect($availability->selectable)->toBeFalse()
        ->and($availability->reason)->toBe('not offered');
});

it('is out of stock when the variant is enabled but unavailable', function (): void {
    $availability = OptionAvailability::resolve('combo-a', ['combo-a' => true], ['combo-a' => false]);

    expect($availability->selectable)->toBeFalse()
        ->and($availability->reason)->toBe('out of stock');
});

it('is selectable when the variant is enabled and available', function (): void {
    $availability = OptionAvailability::resolve('combo-a', ['combo-a' => true], ['combo-a' => true]);

    expect($availability->selectable)->toBeTrue()
        ->and($availability->reason)->toBeNull();
});
