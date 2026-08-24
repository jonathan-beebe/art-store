<?php

declare(strict_types=1);

namespace App\Support\RateLimiting;

use App\Domain\RateLimiting\RateLimitExceeded;
use App\Domain\RateLimiting\RateLimitName;
use App\Domain\RateLimiting\RateLimitValue;
use Illuminate\Cache\RateLimiter;
use Illuminate\Support\Facades\Config;
use Tests\CapturedStory;

it('allows requests under the budget and hits it on each one', function (): void {
    Config::set('rate_limits.checkout', RateLimitValue::parse('3/1m', 'RATE_LIMIT_CHECKOUT'));
    $gate = app(RateLimitGate::class);

    $gate->check(RateLimitName::Checkout, 'cus_A');
    $gate->check(RateLimitName::Checkout, 'cus_A');
    $gate->check(RateLimitName::Checkout, 'cus_A');

    expect(true)->toBeTrue(); // three checks under a budget of three: nothing thrown
});

it('throws once a key has spent its budget, naming the key and a positive wait', function (): void {
    Config::set('rate_limits.checkout', RateLimitValue::parse('1/1m', 'RATE_LIMIT_CHECKOUT'));
    $gate = app(RateLimitGate::class);

    $gate->check(RateLimitName::Checkout, 'cus_A');

    try {
        $gate->check(RateLimitName::Checkout, 'cus_A');
        expect(false)->toBeTrue('the second check should have thrown');
    } catch (RateLimitExceeded $exceeded) {
        expect($exceeded->limit)->toBe(RateLimitName::Checkout)
            ->and($exceeded->key)->toBe('cus_A')
            ->and($exceeded->retryAfterSeconds)->toBeGreaterThan(0);
    }
});

it('trips one key without touching another key under the same limit', function (): void {
    Config::set('rate_limits.checkout', RateLimitValue::parse('1/1m', 'RATE_LIMIT_CHECKOUT'));
    $gate = app(RateLimitGate::class);

    $gate->check(RateLimitName::Checkout, 'cus_A');

    expect(fn () => $gate->check(RateLimitName::Checkout, 'cus_A'))->toThrow(RateLimitExceeded::class);

    // cus_B never trips: cus_A spending its budget left no mark on it.
    $gate->check(RateLimitName::Checkout, 'cus_B');
});

it('logs one rate_limit.exceed line at warn when a check trips', function (): void {
    Config::set('rate_limits.magic_link_request', RateLimitValue::parse('1/15m', 'RATE_LIMIT_MAGIC_LINK_REQUEST'));
    $gate = app(RateLimitGate::class);
    $gate->check(RateLimitName::MagicLinkRequest, 'email:abc123');

    $log = CapturedStory::capture();

    try {
        $gate->check(RateLimitName::MagicLinkRequest, 'email:abc123');
    } catch (RateLimitExceeded) {
        // asserted below
    }

    $line = $log->line('rate_limit.exceed', 'refused');

    expect($line['level'])->toBe('warn')
        ->and($line['data'])->toMatchArray([
            'limit' => 'magic_link_request',
            'key' => 'email:abc123',
        ])
        ->and($line['data'])->toHaveKey('retry_after_seconds');
});

it('does nothing when the limit is off, no matter how many requests', function (): void {
    Config::set('rate_limits.checkout', RateLimitValue::parse('off', 'RATE_LIMIT_CHECKOUT'));
    $gate = app(RateLimitGate::class);

    for ($i = 0; $i < 50; $i++) {
        $gate->check(RateLimitName::Checkout, 'cus_A');
    }

    expect(true)->toBeTrue(); // fifty checks against a disabled limit: nothing thrown
});

it('checks every key before hitting any of them, so a trip on one key leaves the other untouched', function (): void {
    Config::set('rate_limits.magic_link_request', RateLimitValue::parse('1/15m', 'RATE_LIMIT_MAGIC_LINK_REQUEST'));
    $gate = app(RateLimitGate::class);

    // Spends the "ip" budget alone, so the next two-key check trips on ip
    // without ever reaching the hit that would spend "email" too.
    $gate->check(RateLimitName::MagicLinkRequest, 'ip:203.0.113.1');

    expect(fn () => $gate->checkEach(RateLimitName::MagicLinkRequest, ['email:x', 'ip:203.0.113.1']))
        ->toThrow(RateLimitExceeded::class);

    // "email:x" was never hit by the failed two-key attempt above.
    $gate->check(RateLimitName::MagicLinkRequest, 'email:x');
});

it('keeps its count in the cache store rather than on the gate instance, so it survives a process restart', function (): void {
    Config::set('cache.default', 'database');
    Config::set('rate_limits.checkout', RateLimitValue::parse('2/1m', 'RATE_LIMIT_CHECKOUT'));

    // Each `app(RateLimiter::class)` call below builds a fresh instance —
    // Illuminate\Cache\RateLimiter is not bound as a singleton — standing in
    // for the object a restarted process would construct on its own. The
    // budget still runs out on schedule only because the count lives in the
    // database cache table behind it, not on either instance.
    $firstProcess = new RateLimitGate(app(RateLimiter::class));
    $firstProcess->check(RateLimitName::Checkout, 'cus_A');

    $restarted = new RateLimitGate(app(RateLimiter::class));
    $restarted->check(RateLimitName::Checkout, 'cus_A');

    expect(fn () => $restarted->check(RateLimitName::Checkout, 'cus_A'))->toThrow(RateLimitExceeded::class);
});
