<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Auth\ActorType;
use App\Domain\Auth\MagicLinkStatus;
use App\Domain\Auth\MagicLinkToken;
use Illuminate\Database\QueryException;

$link = fn (string $token): MagicLink => MagicLink::factory()->create([
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

    expect($created->statusAt(now()->toDateTimeImmutable()))->toBe(MagicLinkStatus::Usable);
});

it('reports a link expired past its window', function () use ($link): void {
    $created = $link('open-sesame');

    expect($created->statusAt(now()->addMinutes(16)->toDateTimeImmutable()))->toBe(MagicLinkStatus::Expired);
});

it('stamps and closes a link once consumed', function () use ($link): void {
    $created = $link('open-sesame');

    expect($created->consume(now()->toDateTimeImmutable()))->toBeTrue()
        ->and($created->refresh()->consumed_at)->not->toBeNull()
        ->and($created->refresh()->statusAt(now()->toDateTimeImmutable()))->toBe(MagicLinkStatus::Consumed);
});

it('rejects a second link with the same token hash', function () use ($link): void {
    $link('open-sesame');

    expect(fn () => $link('open-sesame'))->toThrow(QueryException::class);
});

it('hands the link to one consumer and turns the second away', function () use ($link): void {
    $created = $link('open-sesame');
    $racing = MagicLink::query()->findOrFail($created->id);
    $stampedAt = now()->toDateTimeImmutable();

    // The second consumer read the row while it was still usable — the write
    // is what settles which of them got it, so the row count is the answer.
    $first = $created->consume($stampedAt);
    $second = $racing->consume(now()->addMinute()->toDateTimeImmutable());

    expect($first)->toBeTrue()
        ->and($second)->toBeFalse()
        ->and($created->refresh()->consumed_at?->toDateTimeString())
        ->toBe($stampedAt->format('Y-m-d H:i:s'));
});
