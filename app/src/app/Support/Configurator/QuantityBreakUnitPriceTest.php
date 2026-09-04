<?php

declare(strict_types=1);

namespace App\Support\Configurator;

use App\Domain\Configurator\QuantityDiscount;
use App\Domain\Money\Money;

it('shaves a tier discount off the base price', function (int $discountBps, string $formatted): void {
    $basePrice = Money::fromCents(450);

    $result = QuantityBreakUnitPrice::resolve($basePrice, QuantityDiscount::of(50, $discountBps));

    expect($result->format())->toBe($formatted);
})->with([
    '10% off $4.50' => [1000, '$4.05'],
    '22% off $4.50' => [2200, '$3.51'],
]);
