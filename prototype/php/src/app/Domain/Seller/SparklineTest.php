<?php

declare(strict_types=1);

namespace App\Domain\Seller;

it('spreads the points across the width, oldest at the left', function (): void {
    $sparkline = Sparkline::of([0, 5, 10], 120, 32);

    expect($sparkline->points)->toBe('0.0,30.0 60.0,16.0 120.0,2.0');
});

it('names the last point, which the tile marks with a dot', function (): void {
    $sparkline = Sparkline::of([0, 5, 10], 120, 32);

    expect($sparkline->endX)->toBe('120.0')
        ->and($sparkline->endY)->toBe('2.0');
});

it('keeps a flat series inside the box', function (): void {
    $sparkline = Sparkline::of([4, 4, 4], 120, 32);

    expect($sparkline->points)->toBe('0.0,30.0 60.0,30.0 120.0,30.0');
});

it('scales against the series own floor, so a high plateau still shows its dip', function (): void {
    $sparkline = Sparkline::of([100, 90, 100], 120, 32);

    expect($sparkline->points)->toBe('0.0,2.0 60.0,30.0 120.0,2.0');
});

it('draws a series with fewer than two days as a flat line across the box', function (int $days): void {
    $sparkline = Sparkline::of(array_slice([7], 0, $days), 120, 32);

    expect($sparkline->points)->toBe('0.0,30.0 120.0,30.0')
        ->and($sparkline->endX)->toBe('120.0')
        ->and($sparkline->endY)->toBe('30.0');
})->with([
    'no days' => [0],
    'one day' => [1],
]);

it('reads the inset off the height it is given', function (): void {
    $sparkline = Sparkline::of([0, 10], 60, 20);

    expect($sparkline->points)->toBe('0.0,18.0 60.0,2.0');
});
