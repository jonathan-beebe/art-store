<?php

declare(strict_types=1);

namespace App\Domain\Analytics;

it('reads a share of the first step as a whole percentage', function (): void {
    expect(FunnelShare::of(34, 100))->toBe(34);
});

it('rounds to the nearest whole percentage', function (): void {
    expect(FunnelShare::of(1, 3))->toBe(33);
});

it('floors a real, nonzero share at 2%, so a sliver still reads as data', function (): void {
    expect(FunnelShare::of(1, 1000))->toBe(2);
});

it('reads a first step of zero as an empty 0%, not a division', function (): void {
    expect(FunnelShare::of(0, 0))->toBe(0);
});

it('floors a true zero at 2% too, once there is a first step to be a share of', function (): void {
    expect(FunnelShare::of(0, 100))->toBe(2);
});

it('reads the first step against itself as 100%', function (): void {
    expect(FunnelShare::of(100, 100))->toBe(100);
});
