<?php

declare(strict_types=1);

namespace App\Domain\RateLimiting;

use RuntimeException;

it('is a runtime exception naming the limit it caught', function (): void {
    $exceeded = new RateLimitExceeded(RateLimitName::Checkout, 'cus_01J0000000000000000000001', 42);

    expect($exceeded)->toBeInstanceOf(RuntimeException::class)
        ->and($exceeded->getMessage())->toBe('Too many requests for checkout.')
        ->and($exceeded->limit)->toBe(RateLimitName::Checkout)
        ->and($exceeded->key)->toBe('cus_01J0000000000000000000001')
        ->and($exceeded->retryAfterSeconds)->toBe(42);
});

it('rounds the retry wait up to whole minutes, at least one', function (int $seconds, int $minutes): void {
    expect((new RateLimitExceeded(RateLimitName::Checkout, 'k', $seconds))->retryAfterMinutes())->toBe($minutes);
})->with([
    'well under a minute' => [5, 1],
    'exactly one minute' => [60, 1],
    'just over a minute' => [61, 2],
    'several whole minutes' => [900, 15],
]);
