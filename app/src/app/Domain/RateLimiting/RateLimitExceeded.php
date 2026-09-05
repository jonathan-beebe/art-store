<?php

declare(strict_types=1);

namespace App\Domain\RateLimiting;

use RuntimeException;

/**
 * A budget `AppRateLimitingRateLimitGate` caught already spent.
 * `key` is already safe to log by the time this is thrown — `RateLimitGate`
 * never hands an email address to it — so a catcher writes it straight into
 * a log line's `data` (docs/spec.md §2.3's `rate_limit.exceed`) without
 * redacting anything further.
 *
 * A trip is a budget the shell measures before the core is ever asked.
 * `RateLimitExceeded` extends `RuntimeException` for that reason.
 * `bootstrap/app.php` gives it its own render step. `back()->withErrors()`
 * renders every `DomainRuleViolation`.
 */
final class RateLimitExceeded extends RuntimeException
{
    private const int SECONDS_PER_MINUTE = 60;

    public function __construct(
        public readonly RateLimitName $limit,
        public readonly string $key,
        public readonly int $retryAfterSeconds,
    ) {
        parent::__construct("Too many requests for {$limit->value}.");
    }

    /**
     * "Too many requests — try again in N minutes" rounds up: a visitor told
     * to wait 1 minute for a 61-second budget who tries again at the minute
     * mark is still short.
     */
    public function retryAfterMinutes(): int
    {
        return max(1, (int) ceil($this->retryAfterSeconds / self::SECONDS_PER_MINUTE));
    }
}
