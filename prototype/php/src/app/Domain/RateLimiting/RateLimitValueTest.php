<?php

declare(strict_types=1);

namespace App\Domain\RateLimiting;

use InvalidArgumentException;

it('parses a count and window into seconds, per unit', function (string $raw, int $maxAttempts, int $decaySeconds): void {
    $value = RateLimitValue::parse($raw, 'RATE_LIMIT_TEST');

    expect($value->enabled)->toBeTrue()
        ->and($value->maxAttempts)->toBe($maxAttempts)
        ->and($value->decaySeconds)->toBe($decaySeconds);
})->with([
    'seconds' => ['5/30s', 5, 30],
    'minutes' => ['5/15m', 5, 900],
    'hours' => ['5/1h', 5, 3600],
    'a zero-length window is not rejected, decaying instantly' => ['5/0s', 5, 0],
]);

it('disables the limit for "off"', function (): void {
    $value = RateLimitValue::parse('off', 'RATE_LIMIT_TEST');

    expect($value->enabled)->toBeFalse()
        ->and($value->maxAttempts)->toBe(0)
        ->and($value->decaySeconds)->toBe(0);
});

it('refuses a malformed value and names the variable that holds it', function (string $raw): void {
    expect(fn () => RateLimitValue::parse($raw, 'RATE_LIMIT_TEST'))
        ->toThrow(InvalidArgumentException::class, "RATE_LIMIT_TEST must be \"<count>/<window>\" (window like 30s, 15m, or 1h) or \"off\", got \"{$raw}\".");
})->with([
    'a malformed count' => ['five/15m'],
    'a fractional count' => ['5.5/15m'],
    'a malformed window' => ['5/15x'],
    'a window with no unit' => ['5/15'],
    'a missing slash' => ['515m'],
    'a zero count' => ['0/15m'],
    'a negative count' => ['-5/15m'],
    'surrounding whitespace' => [' 5/15m '],
    'an empty string' => [''],
]);
