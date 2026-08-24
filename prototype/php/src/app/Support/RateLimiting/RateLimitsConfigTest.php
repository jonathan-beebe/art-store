<?php

declare(strict_types=1);

namespace App\Support\RateLimiting;

use App\Domain\RateLimiting\RateLimitName;
use App\Domain\RateLimiting\RateLimitValue;
use Illuminate\Support\Env;
use InvalidArgumentException;

/**
 * config/rate_limits.php runs `RateLimitValue::parse()` over every env
 * variable while it loads, and that file loads on every boot — before a
 * request is ever routed, the way every other config file does. These
 * exercise that file directly rather than the parser it calls, which
 * `App\Domain\RateLimiting\RateLimitValueTest` already covers on its own.
 *
 * `env()` answers from Dotenv's repository, which `.env` fills at boot, so
 * these write through that repository rather than `putenv()`, whose value it
 * shadows. Each case starts from all seven variables cleared and gets back
 * whatever `.env` gave them, so the file reads the same on a checkout that
 * sets them and one that does not.
 */

/** @var array<string, string|null> $shipped */
$shipped = [];

beforeEach(function () use (&$shipped): void {
    $repository = Env::getRepository();

    foreach (RateLimitName::cases() as $limit) {
        $shipped[$limit->envVariable()] = $repository->get($limit->envVariable());
        $repository->clear($limit->envVariable());
    }
});

afterEach(function () use (&$shipped): void {
    $repository = Env::getRepository();

    foreach ($shipped as $variable => $value) {
        if ($value === null) {
            $repository->clear($variable);
        } else {
            $repository->set($variable, $value);
        }
    }
});

it('refuses to boot when a rate limit env variable is malformed', function (): void {
    Env::getRepository()->set('RATE_LIMIT_CHECKOUT', 'not-a-limit');

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
