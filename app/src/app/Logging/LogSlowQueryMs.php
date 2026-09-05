<?php

declare(strict_types=1);

namespace App\Logging;

use InvalidArgumentException;

/**
 * `LOG_SLOW_QUERY_MS` per docs/spec.md §2.3: a positive integer number
 * of milliseconds, or `"off"` to disable the `query.exceed` line. `parse()`
 * is the one place a raw env string becomes that value; `config/log_store.php`
 * calls it while the config file loads, so a malformed value throws at boot
 * rather than on the first query that would have needed it — the same
 * eager-parse shape as `LogRetentionDays::parse`.
 */
final readonly class LogSlowQueryMs
{
    private const string OFF = 'off';

    private const string PATTERN = '/^\d+$/';

    private function __construct(public ?int $milliseconds) {}

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

        $milliseconds = (int) $raw;

        if ($milliseconds < 1) {
            throw self::malformed($variable, $raw);
        }

        return new self($milliseconds);
    }

    private static function malformed(string $variable, string $raw): InvalidArgumentException
    {
        return new InvalidArgumentException(
            "{$variable} must be a positive integer or \"off\", got \"{$raw}\".",
        );
    }
}
