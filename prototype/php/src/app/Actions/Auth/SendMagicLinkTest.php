<?php

declare(strict_types=1);

namespace App\Actions\Auth;

use App\Domain\Auth\ActorType;
use App\Domain\Auth\MagicLinkToken;
use App\Models\MagicLink;
use App\Notifications\MagicLinkIssued;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Notification;
use RuntimeException;
use Tests\CapturedStory;

$send = function (string $email, ActorType $actorType, ?string $redirectTo = null): string {
    app(SendMagicLink::class)($email, $actorType, $redirectTo, now()->toDateTimeImmutable());

    $url = session('debug_magic_link');

    return is_string($url)
        ? $url
        : throw new RuntimeException('SendMagicLink flashed no link to the session.');
};

it('stores only the hash of the token it delivers', function () use ($send): void {
    $url = $send('Artist@Example.com', ActorType::Seller);
    $path = parse_url($url, PHP_URL_PATH);
    expect($path)->toBeString();
    $token = basename((string) $path);

    $tokenHash = MagicLink::sole()->token_hash;

    expect($tokenHash)->toBe(MagicLinkToken::hash($token))
        ->and($tokenHash)->not->toContain($token);
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

it('sends the link to the address that asked for it', function (): void {
    Notification::fake();

    app(SendMagicLink::class)('Artist@Example.com', ActorType::Seller, null, now()->toDateTimeImmutable());

    Notification::assertSentOnDemand(
        MagicLinkIssued::class,
        fn (MagicLinkIssued $notification, array $channels, AnonymousNotifiable $notifiable): bool => $notifiable->routes[MagicLinkIssued::channel()] === 'artist@example.com',
    );
});

it('flashes the link for the debug alert to render', function () use ($send): void {
    expect($send('artist@example.com', ActorType::Seller))->toContain('/auth/magic/');
});

it('tells the story of issuing a link without writing the address or the token', function () use ($send): void {
    $log = CapturedStory::capture();

    $url = $send('Artist@Example.com', ActorType::Seller);
    $token = basename((string) parse_url($url, PHP_URL_PATH));

    expect($log->outline())->toContain('magic_link.request will', 'magic_link.request did')
        ->and($log->line('magic_link.request', 'did')['data'])
        ->toHaveKey('magic_link_id', MagicLink::sole()->id)
        ->toHaveKey('actor_type', 'seller');

    $written = $log->raw();

    expect($written)->not->toContain($token);
    expect($written)->not->toContain('artist@example.com');
    expect($written)->not->toContain('Artist@Example.com');
});
