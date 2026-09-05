<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\QueryException;

const A_TOKEN = 'artstore_0123456789012345678901234567890123456789';

it('finds an active key by the token behind its hash', function (): void {
    $key = ApiKey::factory()->forToken(A_TOKEN)->create();

    expect(ApiKey::forToken(A_TOKEN)->active()->sole()->is($key))->toBeTrue()
        ->and(ApiKey::forToken('artstore_guess')->first())->toBeNull();
});

it('never stores the token itself and never serialises the digest', function (): void {
    $key = ApiKey::factory()->forToken(A_TOKEN)->create();

    expect($key->toArray())->not->toHaveKey('token_hash')
        ->and($key->getAttribute('token_hash'))->not->toContain('artstore_');
});

it('refuses two keys with the same digest', function (): void {
    ApiKey::factory()->forToken(A_TOKEN)->create();

    ApiKey::factory()->forToken(A_TOKEN)->create();
})->throws(QueryException::class);

it('drops out of the active set once revoked, and revokes only once', function (): void {
    $key = ApiKey::factory()->forToken(A_TOKEN)->create();
    $first = $this->moment('2026-09-05 10:00:00');

    $key->revoke($first);
    $key->revoke($this->moment('2026-09-05 11:00:00'));

    expect($key->refresh()->isRevoked())->toBeTrue()
        ->and($key->revoked_at?->getTimestamp())->toBe($first->getTimestamp())
        ->and(ApiKey::forToken(A_TOKEN)->active()->first())->toBeNull();
});

it('records a use at most once a minute', function (): void {
    $key = ApiKey::factory()->create();

    $key->markUsed($this->moment('2026-09-05 10:00:00'));
    $key->markUsed($this->moment('2026-09-05 10:00:30'));

    expect($key->refresh()->last_used_at?->getTimestamp())->toBe($this->moment('2026-09-05 10:00:00')->getTimestamp());

    $key->markUsed($this->moment('2026-09-05 10:01:00'));

    expect($key->refresh()->last_used_at?->getTimestamp())->toBe($this->moment('2026-09-05 10:01:00')->getTimestamp());
});

it('belongs to the admin who minted it and goes with them', function (): void {
    $admin = Admin::factory()->create();
    $key = ApiKey::factory()->for($admin)->create();

    expect($key->admin?->is($admin))->toBeTrue();

    $admin->delete();

    expect(ApiKey::find($key->id))->toBeNull();
});
