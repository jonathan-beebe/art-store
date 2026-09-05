<?php

declare(strict_types=1);

namespace App\Actions\ApiKeys;

use App\Domain\Auth\ApiKeyToken;
use App\Models\Admin;
use App\Models\ApiKey;

it('mints a well-formed key the store can find by its plaintext', function (): void {
    $admin = Admin::factory()->create();

    $minted = app(MintApiKey::class)($admin, 'Claude Code');

    expect(ApiKeyToken::isWellFormed($minted->plaintext))->toBeTrue()
        ->and($minted->key->admin_id)->toBe($admin->id)
        ->and($minted->key->name)->toBe('Claude Code')
        ->and($minted->key->id)->toStartWith('key_')
        ->and(ApiKey::forToken($minted->plaintext)->active()->sole()->is($minted->key))->toBeTrue();
});

it('mints a different secret every time', function (): void {
    $admin = Admin::factory()->create();

    $first = app(MintApiKey::class)($admin, 'one');
    $second = app(MintApiKey::class)($admin, 'two');

    expect($first->plaintext)->not->toBe($second->plaintext)
        ->and(ApiKey::count())->toBe(2);
});
