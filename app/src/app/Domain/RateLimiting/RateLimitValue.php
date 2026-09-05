<?php

declare(strict_types=1);

namespace App\Domain\RateLimiting;

use InvalidArgumentException;

/**
 * The `<count>/<window>` shape docs/spec.md §3 gives every rate limit's
 * env variable, or `"off"` to disable it. `parse()` is the one place a raw
 * env string becomes a budget; `config/rate_limits.php` calls it while the
 * config file loads, so a malformed value throws at boot, before any
 * request needs it.
 */
final readonly class RateLimitValue
{
    private const string OFF = 'off';

    /**
     * A count of one or more digits, a slash, a window of one or more
     * digits, and a single unit letter. No surrounding whitespace: an env
     * variable that needs trimming is malformed the same as one with a typo.
     */
    private const string PATTERN = '/^(\d+)\/(\d+)([smh])$/';

    private const array SECONDS_PER_UNIT = ['s' => 1, 'm' => 60, 'h' => 3600];

    private function __construct(
        public bool $enabled,
        public int $maxAttempts,
        public int $decaySeconds,
    ) {}

    /**
     * @param  string  $variable  the env variable's name, so a malformed
     *                            value's exception says which setting to fix
     */
    public static function parse(string $raw, string $variable): self
    {
        if ($raw === self::OFF) {
            return new self(enabled: false, maxAttempts: 0, decaySeconds: 0);
        }

        if (preg_match(self::PATTERN, $raw, $parts) !== 1) {
            throw self::malformed($variable, $raw);
        }

        [, $count, $window, $unit] = $parts;
        $maxAttempts = (int) $count;

        if ($maxAttempts < 1) {
            throw self::malformed($variable, $raw);
        }

        return new self(
            enabled: true,
            maxAttempts: $maxAttempts,
            decaySeconds: ((int) $window) * self::SECONDS_PER_UNIT[$unit],
        );
    }

    private static function malformed(string $variable, string $raw): InvalidArgumentException
    {
        return new InvalidArgumentException(
            "{$variable} must be \"<count>/<window>\" (window like 30s, 15m, or 1h) or \"off\", got \"{$raw}\".",
        );
    }
}
