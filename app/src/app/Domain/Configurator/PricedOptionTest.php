<?php

declare(strict_types=1);

use App\Domain\Configurator\PricedOption;
use App\Domain\Money\Money;

it('amounts to its absolute price on a standalone axis', function (): void {
    $option = PricedOption::of('ovl_1', 'Size', '8 × 10', true, Money::fromCents(4500), Money::fromCents(0));

    expect($option->amount())->toBeMoney(4500);
});

it('amounts to its signed surcharge on an add-on axis', function (): void {
    $option = PricedOption::of('ovl_2', 'Frame', 'Black', false, Money::fromCents(0), Money::fromCents(-500));

    expect($option->amount())->toBeMoney(-500);
});
