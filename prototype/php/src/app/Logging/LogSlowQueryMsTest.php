<?php

declare(strict_types=1);

namespace App\Logging;

use InvalidArgumentException;

it('parses a positive integer number of milliseconds', function (): void {
    expect(LogSlowQueryMs::parse('50', 'LOG_SLOW_QUERY_MS')->milliseconds)->toBe(50)
        ->and(LogSlowQueryMs::parse('1', 'LOG_SLOW_QUERY_MS')->milliseconds)->toBe(1);
});

it('disables the slow-query line for "off"', function (): void {
    expect(LogSlowQueryMs::parse('off', 'LOG_SLOW_QUERY_MS')->milliseconds)->toBeNull();
});

it('refuses a malformed value and names the variable that holds it', function (string $raw): void {
    expect(fn () => LogSlowQueryMs::parse($raw, 'LOG_SLOW_QUERY_MS'))
        ->toThrow(InvalidArgumentException::class, "LOG_SLOW_QUERY_MS must be a positive integer or \"off\", got \"{$raw}\".");
})->with([
    'a fractional value' => ['50.5'],
    'a negative value' => ['-50'],
    'zero' => ['0'],
    'words' => ['fast'],
    'surrounding whitespace' => [' 50 '],
    'an empty string' => [''],
]);
