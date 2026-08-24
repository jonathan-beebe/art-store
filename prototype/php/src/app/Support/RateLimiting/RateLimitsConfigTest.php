<?php

declare(strict_types=1);

namespace App\Support\RateLimiting;

use App\Domain\RateLimiting\RateLimitValue;
use InvalidArgumentException;

/**
 * config/rate_limits.php runs `RateLimitValue::parse()` over every env
 * variable while it loads, and that file loads on every boot — before a
 * request is ever routed, the way every other config file does. These
 * exercise that file directly rather than the parser it calls, which
 * `App\Domain\RateLimiting\RateLimitValueTest` already covers on its own.
 */
it('refuses to boot when a rate limit env variable is malformed', function (): void {
    putenv('RATE_LIMIT_CHECKOUT=not-a-limit');

    try {
        expect(fn () => require config_path('rate_limits.php'))
            ->toThrow(InvalidArgumentException::class, 'RATE_LIMIT_CHECKOUT must be');
    } finally {
        putenv('RATE_LIMIT_CHECKOUT');
    }
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
