<?php

declare(strict_types=1);

namespace App\Configurator;

use InvalidArgumentException;

it('converts a percent to basis points', function (string $percent, int $bps): void {
    expect(QuantityBreakPercent::toBps($percent))->toBe($bps);
})->with([
    '10 -> 1000' => ['10', 1000],
    '12.5 -> 1250' => ['12.5', 1250],
    '0.01 -> 1' => ['0.01', 1],
    '99.99 -> 9999' => ['99.99', 9999],
    '7.05 -> 705' => ['7.05', 705],
]);

it('rejects a percent outside 0.01 to 99.99', function (string $percent): void {
    expect(QuantityBreakPercent::isValid($percent))->toBeFalse();
    QuantityBreakPercent::toBps($percent);
})->with([
    'over the cap' => ['100'],
    'zero' => ['0'],
    'negative' => ['-5'],
    'three decimals' => ['12.555'],
    'not a number' => ['ten'],
])->throws(InvalidArgumentException::class);

it('formats basis points back to the percent a seller typed', function (int $bps, string $percent): void {
    expect(QuantityBreakPercent::format($bps))->toBe($percent);
})->with([
    '1000 -> 10' => [1000, '10'],
    '1250 -> 12.5' => [1250, '12.5'],
    '1 -> 0.01' => [1, '0.01'],
    '9999 -> 99.99' => [9999, '99.99'],
    '705 -> 7.05' => [705, '7.05'],
]);
