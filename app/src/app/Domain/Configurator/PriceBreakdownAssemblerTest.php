<?php

declare(strict_types=1);

namespace App\Domain\Configurator;

use App\Domain\Money\Money;

it('scales every line by the quantity with no tier', function (): void {
    $breakdown = PriceBreakdownAssembler::assemble([
        PriceBreakdownLine::of('Base price', Money::fromCents(2000)),
        PriceBreakdownLine::of('Rose Gold', Money::fromCents(800)),
    ], 3, null);

    expect($breakdown->lines[0]->amount->cents)->toBe(6000)
        ->and($breakdown->lines[1]->amount->cents)->toBe(2400)
        ->and($breakdown->total()->cents)->toBe(8400);
});

it('carries each line’s signedness through the quantity scaling', function (): void {
    // Regression guard: scaling used to rebuild every line through
    // `PriceBreakdownLine::of()`'s two-argument form, silently defaulting
    // every line back to signed regardless of what it started as.
    $breakdown = PriceBreakdownAssembler::assemble([
        PriceBreakdownLine::of('Size: 8x10', Money::fromCents(1800), signed: false),
        PriceBreakdownLine::of('Frame: Black frame', Money::fromCents(3200)),
    ], 2, null);

    expect($breakdown->lines[0]->signed)->toBeFalse()
        ->and($breakdown->lines[1]->signed)->toBeTrue();
});

it('appends the tier discount against the scaled subtotal', function (): void {
    $breakdown = PriceBreakdownAssembler::assemble([
        PriceBreakdownLine::of('Base price', Money::fromCents(300)),
    ], 100, QuantityDiscount::of(100, 1000));

    expect($breakdown->lines)->toHaveCount(2)
        ->and($breakdown->lines[1]->label)->toBe('Quantity discount (100+)')
        ->and($breakdown->lines[1]->amount->cents)->toBe(-3000)
        ->and($breakdown->total()->cents)->toBe(27000);
});
