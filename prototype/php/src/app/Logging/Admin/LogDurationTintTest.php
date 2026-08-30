<?php

declare(strict_types=1);

namespace App\Logging\Admin;

it('has no tint for no duration', function (): void {
    expect(LogDurationTint::ofMs(null))->toBeNull();
});

it('tints fast at and under 300ms', function (int $ms): void {
    expect(LogDurationTint::ofMs($ms))->toBe(LogDurationTint::Fast);
})->with([0, 1, 300]);

it('tints slow from 301ms through 600ms', function (int $ms): void {
    expect(LogDurationTint::ofMs($ms))->toBe(LogDurationTint::Slow);
})->with([301, 450, 600]);

it('tints bad past 600ms', function (int $ms): void {
    expect(LogDurationTint::ofMs($ms))->toBe(LogDurationTint::Bad);
})->with([601, 842, 100000]);

it('gives each tint its own text classes, with a dark variant', function (): void {
    expect(LogDurationTint::Fast->textClasses())->toContain('green')->toContain('dark:')
        ->and(LogDurationTint::Slow->textClasses())->toContain('orange')->toContain('dark:')
        ->and(LogDurationTint::Bad->textClasses())->toContain('red')->toContain('dark:');
});
