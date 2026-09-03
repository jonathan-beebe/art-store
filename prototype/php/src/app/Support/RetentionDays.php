<?php

declare(strict_types=1);

namespace App\Support;

use InvalidArgumentException;

/**
 * A retention window in days, the shape both `LOG_RETENTION_DAYS`
 * (docs/alignment.md §2.5) and `ANALYTICS_RETENTION_DAYS` (§2.6) take: a
 * positive integer number of days, or `"off"` to disable pruning entirely.
 * `parse()` is the one place a raw env string becomes that value;
 * `config/log_store.php` and `config/analytics.php` both call it while the
 * config file loads, so a malformed value throws at boot rather than on the
 * sweep that would have needed it — the same eager-parse shape as
 * `App\Domain\RateLimiting\RateLimitValue::parse`.
 */
final readonly class RetentionDays
{
    private const string OFF = 'off';

    private const string PATTERN = '/^\d+$/';

    private function __construct(public ?int $days) {}

    /**
     * @param  string  $variable  the env variable's name, so a malformed
     *                            value's exception says which setting to fix
     */
    public static function parse(string $raw, string $variable): self
    {
        if ($raw === self::OFF) {
            return new self(null);
        }

        if (preg_match(self::PATTERN, $raw) !== 1) {
            throw self::malformed($variable, $raw);
        }

        $days = (int) $raw;

        if ($days < 1) {
            throw self::malformed($variable, $raw);
        }

        return new self($days);
    }

    private static function malformed(string $variable, string $raw): InvalidArgumentException
    {
        return new InvalidArgumentException(
            "{$variable} must be a positive integer or \"off\", got \"{$raw}\".",
        );
    }
}
