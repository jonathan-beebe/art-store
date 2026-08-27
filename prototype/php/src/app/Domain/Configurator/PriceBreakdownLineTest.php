<?php

declare(strict_types=1);

namespace App\Domain\Configurator;

use App\Domain\Money\Money;

it('carries a label and an amount', function (): void {
    $line = PriceBreakdownLine::of('Engraving, both sides', Money::fromCents(850));

    expect($line->label)->toBe('Engraving, both sides')
        ->and($line->amount->cents)->toBe(850);
});
