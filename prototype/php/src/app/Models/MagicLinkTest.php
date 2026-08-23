<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Auth\ActorType;
use App\Domain\Auth\MagicLinkStatus;
use App\Domain\Auth\MagicLinkToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

$link = fn (string $token): MagicLink => MagicLink::create([
    'token_hash' => MagicLinkToken::hash($token),
    'email' => 'artist@example.com',
    'actor_type' => ActorType::Seller,
    'expires_at' => now()->addMinutes(15),
]);

it('finds a link by the token behind its hash', function () use ($link): void {
    $created = $link('open-sesame');

    expect($created->is(MagicLink::forToken('open-sesame')->first()))->toBeTrue();
});

it('finds nothing for a token it never issued', function () use ($link): void {
    $link('open-sesame');

    expect(MagicLink::forToken('guess')->first())->toBeNull();
});

it('reports a fresh link usable', function () use ($link): void {
    $created = $link('open-sesame');

    expect($created->statusAt(now()))->toBe(MagicLinkStatus::Usable);
});

it('reports a link expired past its window', function () use ($link): void {
    $created = $link('open-sesame');

    expect($created->statusAt(now()->addMinutes(16)))->toBe(MagicLinkStatus::Expired);
});

it('stamps and closes a link once consumed', function () use ($link): void {
    $created = $link('open-sesame');

    $created->consume(now());

    expect($created->fresh()->consumed_at)->not->toBeNull();
    expect($created->fresh()->statusAt(now()))->toBe(MagicLinkStatus::Consumed);
});
