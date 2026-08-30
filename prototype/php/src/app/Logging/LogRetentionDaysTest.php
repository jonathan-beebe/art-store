<?php

declare(strict_types=1);

namespace App\Logging;

use InvalidArgumentException;

it('parses a positive integer number of days', function (): void {
    expect(LogRetentionDays::parse('14', 'LOG_RETENTION_DAYS')->days)->toBe(14)
        ->and(LogRetentionDays::parse('1', 'LOG_RETENTION_DAYS')->days)->toBe(1);
});

it('disables retention for "off"', function (): void {
    expect(LogRetentionDays::parse('off', 'LOG_RETENTION_DAYS')->days)->toBeNull();
});

it('refuses a malformed value and names the variable that holds it', function (string $raw): void {
    expect(fn () => LogRetentionDays::parse($raw, 'LOG_RETENTION_DAYS'))
        ->toThrow(InvalidArgumentException::class, "LOG_RETENTION_DAYS must be a positive integer or \"off\", got \"{$raw}\".");
})->with([
    'a fractional value' => ['14.5'],
    'a negative value' => ['-14'],
    'zero' => ['0'],
    'words' => ['forever'],
    'surrounding whitespace' => [' 14 '],
    'an empty string' => [''],
]);
