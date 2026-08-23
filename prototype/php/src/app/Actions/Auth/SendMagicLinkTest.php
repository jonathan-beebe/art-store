<?php

declare(strict_types=1);

namespace App\Actions\Auth;

use App\Domain\Auth\ActorType;
use App\Domain\Auth\MagicLinkToken;
use App\Models\MagicLink;

$send = function (string $email, ActorType $actorType, ?string $redirectTo = null): string {
    app(SendMagicLink::class)($email, $actorType, $redirectTo);

    return session('debug_magic_link');
};

it('stores only the hash of the token it delivers', function () use ($send): void {
    $url = $send('Artist@Example.com', ActorType::Seller);
    $token = basename(parse_url($url, PHP_URL_PATH));

    expect(MagicLink::sole()->token_hash)
        ->toBe(MagicLinkToken::hash($token))
        ->not->toContain($token);
});

it('normalizes the address on the link', function () use ($send): void {
    $send('Artist@Example.com', ActorType::Seller);

    expect(MagicLink::sole()->email)->toBe('artist@example.com');
});

it('records the actor the link signs in', function () use ($send): void {
    $send('shopper@example.com', ActorType::Customer);

    expect(MagicLink::sole()->actor_type)->toBe(ActorType::Customer);
});

it('expires the link after the configured window', function () use ($send): void {
    config(['magic_links.expiry_minutes' => 15]);
    $this->freezeTime();

    $send('artist@example.com', ActorType::Seller);

    expect(MagicLink::sole()->expires_at->format('Y-m-d H:i:s'))
        ->toBe(now()->addMinutes(15)->format('Y-m-d H:i:s'));
});

it('carries the page the visitor was heading for', function () use ($send): void {
    $send('shopper@example.com', ActorType::Customer, '/checkout');

    expect(MagicLink::sole()->redirect_to)->toBe('/checkout');
});
