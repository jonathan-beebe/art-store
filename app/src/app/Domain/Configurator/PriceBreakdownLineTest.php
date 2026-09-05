<?php

declare(strict_types=1);

namespace App\Domain\Configurator;

use App\Domain\Money\Money;

it('carries a label and an amount, signed by default', function (): void {
    $line = PriceBreakdownLine::of('Engraving, both sides', Money::fromCents(850));

    expect($line->label)->toBe('Engraving, both sides')
        ->and($line->amount->cents)->toBe(850)
        ->and($line->signed)->toBeTrue();
});

it('carries an unsigned line for a price shown in its own right', function (): void {
    $line = PriceBreakdownLine::of('Size: 8x10', Money::fromCents(1800), signed: false);

    expect($line->signed)->toBeFalse();
});
