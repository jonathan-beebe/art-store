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
