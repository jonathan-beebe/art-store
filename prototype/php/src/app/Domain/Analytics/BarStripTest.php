<?php

declare(strict_types=1);

namespace App\Domain\Analytics;

it('scales the tallest count to fill the given pixel height', function (): void {
    expect(BarStrip::heights([10, 20, 5], 100))->toBe([50, 100, 25]);
});

it('never renders shorter than 2px, even for a real zero', function (): void {
    expect(BarStrip::heights([0, 1, 100], 26))->toBe([2, 2, 26]);
});

it('renders every bar at the minimum height when every count is zero', function (): void {
    expect(BarStrip::heights([0, 0, 0], 26))->toBe([2, 2, 2]);
});

it('scales an empty series to an empty list of heights', function (): void {
    expect(BarStrip::heights([], 26))->toBe([]);
});

it('pairs each scaled height with a tooltip naming its day', function (): void {
    $bars = BarStrip::bars([10, 20], ['2026-08-19', '2026-08-20'], 100);

    expect($bars)->toHaveCount(2)
        ->and($bars[0]->height)->toBe(50)
        ->and($bars[0]->tip)->toBe('Aug 19: 10')
        ->and($bars[0]->hot)->toBeFalse()
        ->and($bars[1]->height)->toBe(100)
        ->and($bars[1]->tip)->toBe('Aug 20: 20');
});

it('scales a signed series around a zero baseline', function (array $values, array $expectedHeights, array $expectedNegative, int $expectedBaseline): void {
    /** @var list<int> $values */
    $tips = array_map(fn ($value): string => "value {$value}", $values);

    $result = BarStrip::baseline($values, $tips, 100);

    expect(array_map(fn (BarStripBar $bar): int => $bar->height, $result->bars))->toBe($expectedHeights)
        ->and(array_map(fn (BarStripBar $bar): bool => $bar->negative, $result->bars))->toBe($expectedNegative)
        ->and($result->baselinePx)->toBe($expectedBaseline);
})->with([
    'all positive puts the baseline on the bottom edge, same as bars()' => [
        [10, 20, 5], [50, 100, 25], [false, false, false], 100,
    ],
    'all negative mirrors it from the top edge' => [
        [-10, -20, -5], [50, 100, 25], [true, true, true], 0,
    ],
    'mixed splits the strip at the proportional zero line' => [
        [50, -30, 10, -5, 0], [63, 37, 13, 6, 2], [false, true, false, true, false], 63,
    ],
    'an extreme swing never rounds a bar past its own side of the baseline' => [
        [1000, -1], [98, 2], [false, true], 98,
    ],
]);

it('carries each bar\'s own tip through unchanged', function (): void {
    $bars = BarStrip::baseline([10, -5], ['+$0.10', '-$0.05'], 100)->bars;

    expect($bars[0]->tip)->toBe('+$0.10')
        ->and($bars[1]->tip)->toBe('-$0.05');
});

it('never divides by zero when a signed series is empty or every value is zero', function (): void {
    $zeroes = BarStrip::baseline([0, 0], ['a', 'b'], 100);

    expect(BarStrip::baseline([], [], 100)->bars)->toBe([])
        ->and(array_map(fn (BarStripBar $bar): int => $bar->height, $zeroes->bars))->toBe([2, 2])
        ->and($zeroes->baselinePx)->toBe(100);
});
