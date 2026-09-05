<?php

declare(strict_types=1);

namespace App\Domain\RateLimiting;

it('keys the limiter by the full digest of the normalized address', function (): void {
    $key = EmailRateLimitKey::for('Shopper@Example.com');

    expect($key->key())->toBe('email:'.hash('sha256', 'shopper@example.com'));
});

it('normalizes the address the same way for two different castings of it', function (): void {
    expect(EmailRateLimitKey::for('shopper@example.com')->key())
        ->toBe(EmailRateLimitKey::for(' Shopper@Example.com ')->key());
});

it('logs only the first sixteen hex characters of the digest, sha256-prefixed', function (): void {
    $digest = hash('sha256', 'shopper@example.com');

    expect(EmailRateLimitKey::for('shopper@example.com')->logged())
        ->toBe('sha256:'.substr($digest, 0, 16));
});

it('never puts the address itself in either shape it hands out', function (): void {
    $key = EmailRateLimitKey::for('shopper@example.com');

    expect($key->key())->not->toContain('shopper@example.com')
        ->and($key->logged())->not->toContain('shopper@example.com');
});
