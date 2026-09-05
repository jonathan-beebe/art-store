<?php

declare(strict_types=1);

namespace App\Domain\Configurator;

use App\Domain\Money\Money;

it('totals its lines, negative lines included', function (): void {
    $breakdown = PriceBreakdown::of([
        PriceBreakdownLine::of('Base price', Money::fromCents(2000)),
        PriceBreakdownLine::of('Engraving', Money::fromCents(850)),
        PriceBreakdownLine::of('Quantity discount', Money::fromCents(-200)),
    ]);

    expect($breakdown->total()->cents)->toBe(2650);
});

it('totals to zero with no lines', function (): void {
    expect(PriceBreakdown::of([])->total()->cents)->toBe(0);
});
