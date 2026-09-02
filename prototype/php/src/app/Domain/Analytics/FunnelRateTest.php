<?php

declare(strict_types=1);

namespace App\Domain\Analytics;

it('reads a share of its prerequisite as a whole percentage and its ratio', function (): void {
    $rate = FunnelRate::of(34, 100, 'views');

    expect($rate)->not->toBeNull()
        ->and($rate?->text)->toBe('34%')
        ->and($rate?->ratio)->toBe(0.34);
});

it('rounds the percentage to the nearest whole number', function (): void {
    $rate = FunnelRate::of(1, 3, 'views');

    expect($rate?->text)->toBe('33%');
});

it('reads a step with nothing before it as no rate at all', function (): void {
    expect(FunnelRate::of(5, 0, 'views'))->toBeNull();
});

it('reads a current count of zero as a real zero percent, not a missing rate', function (): void {
    $rate = FunnelRate::of(0, 40, 'views');

    expect($rate?->text)->toBe('0%')
        ->and($rate?->ratio)->toBe(0.0);
});

it('carries the prerequisite step\'s own label', function (): void {
    $rate = FunnelRate::of(34, 100, 'checkouts opened');

    expect($rate?->ofLabel)->toBe('checkouts opened');
});
