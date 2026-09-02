<?php

declare(strict_types=1);

namespace App\Support;

use InvalidArgumentException;

it('parses a positive integer number of days', function (): void {
    expect(RetentionDays::parse('14', 'LOG_RETENTION_DAYS')->days)->toBe(14)
        ->and(RetentionDays::parse('1', 'LOG_RETENTION_DAYS')->days)->toBe(1);
});

it('disables retention for "off"', function (): void {
    expect(RetentionDays::parse('off', 'LOG_RETENTION_DAYS')->days)->toBeNull();
});

it('refuses a malformed value and names the variable that holds it', function (string $raw): void {
    expect(fn () => RetentionDays::parse($raw, 'LOG_RETENTION_DAYS'))
        ->toThrow(InvalidArgumentException::class, "LOG_RETENTION_DAYS must be a positive integer or \"off\", got \"{$raw}\".");
})->with([
    'a fractional value' => ['14.5'],
    'a negative value' => ['-14'],
    'zero' => ['0'],
    'words' => ['forever'],
    'surrounding whitespace' => [' 14 '],
    'an empty string' => [''],
]);

it('names whichever variable the caller passes, so LOG_RETENTION_DAYS and ANALYTICS_RETENTION_DAYS read the same parser', function (): void {
    expect(RetentionDays::parse('30', 'ANALYTICS_RETENTION_DAYS')->days)->toBe(30);
    expect(fn () => RetentionDays::parse('never', 'ANALYTICS_RETENTION_DAYS'))
        ->toThrow(InvalidArgumentException::class, 'ANALYTICS_RETENTION_DAYS must be a positive integer or "off", got "never".');
});
