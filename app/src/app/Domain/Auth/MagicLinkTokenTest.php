<?php

declare(strict_types=1);

namespace App\Domain\Auth;

it('hashes a token with sha256', function (): void {
    expect(MagicLinkToken::hash('abc'))->toBe(hash('sha256', 'abc'));
});

it('hashes the same token to the same digest', function (): void {
    expect(MagicLinkToken::hash('abc'))->toBe(MagicLinkToken::hash('abc'));
});

it('hashes different tokens to different digests', function (): void {
    expect(MagicLinkToken::hash('abc'))->not->toBe(MagicLinkToken::hash('abd'));
});
