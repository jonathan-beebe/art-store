<?php

declare(strict_types=1);

namespace App\Support\RateLimiting;

use App\Domain\RateLimiting\RateLimitName;
use App\Domain\RateLimiting\RateLimitValue;
use InvalidArgumentException;

/**
 * config/rate_limits.php runs `RateLimitValue::parse()` over every env
 * variable while it loads, and that file loads on every boot — before a
 * request is ever routed, the way every other config file does. These
 * exercise that file directly rather than the parser it calls, which
 * `App\Domain\RateLimiting\RateLimitValueTest` already covers on its own.
 *
 * These write `$_ENV`/`$_SERVER`/`putenv()` directly rather than through
 * `Illuminate\Support\Env::getRepository()`: that repository is immutable
 * once a process has ever seen a value for a key (`.env` supplies every
 * `RATE_LIMIT_*` variable on every boot), so a second `set()` for the same
 * key silently no-ops under Pest's `--parallel` worker, which boots the
 * application once before running a file's tests rather than once per
 * process the way a serial run does. `env()` still reads through that same
 * repository, and its reader chain checks `$_SERVER`, then `$_ENV`, then
 * `putenv()`, so a case that only wrote one of the three would still read
 * back a stale value out of the others. Each case starts from all seven
 * variables cleared and gets back whatever `.env` gave them, so the file
 * reads the same on a checkout that sets them and one that does not.
 */
function setRateLimitEnv(string $variable, ?string $value): void
{
    if ($value === null) {
        putenv($variable);
        unset($_ENV[$variable], $_SERVER[$variable]);

        return;
    }

    putenv("{$variable}={$value}");
    $_ENV[$variable] = $value;
    $_SERVER[$variable] = $value;
}

/** @var array<string, string|null> $shipped */
$shipped = [];

beforeEach(function () use (&$shipped): void {
    foreach (RateLimitName::cases() as $limit) {
        $shipped[$limit->envVariable()] = getenv($limit->envVariable()) ?: null;
        setRateLimitEnv($limit->envVariable(), null);
    }
});

afterEach(function () use (&$shipped): void {
    foreach ($shipped as $variable => $value) {
        setRateLimitEnv($variable, $value);
    }
});

it('refuses to boot when a rate limit env variable is malformed', function (): void {
    setRateLimitEnv('RATE_LIMIT_CHECKOUT', 'not-a-limit');

    expect(fn () => require config_path('rate_limits.php'))
        ->toThrow(InvalidArgumentException::class, 'RATE_LIMIT_CHECKOUT must be');
});

it('reads the docs/alignment.md §3 default for every limit when nothing is set', function (): void {
    /** @var array<string, RateLimitValue> $limits */
    $limits = require config_path('rate_limits.php');

    expect($limits['magic_link_request']->maxAttempts)->toBe(5)
        ->and($limits['magic_link_request']->decaySeconds)->toBe(900)
        ->and($limits['magic_link_consume']->decaySeconds)->toBe(900)
        ->and($limits['message_post']->decaySeconds)->toBe(3600)
        ->and($limits['conversation_open']->decaySeconds)->toBe(3600)
        ->and($limits['checkout']->decaySeconds)->toBe(3600)
        ->and($limits['payment_attempt']->decaySeconds)->toBe(900)
        ->and($limits['listing_write']->decaySeconds)->toBe(3600);
});
