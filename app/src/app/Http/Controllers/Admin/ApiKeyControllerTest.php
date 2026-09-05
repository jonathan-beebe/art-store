<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Domain\Auth\ApiKeyToken;
use App\Models\Admin;
use App\Models\ApiKey;

it('lists the signed-in admins own keys, newest first, and nobody elses', function (): void {
    $admin = $this->admin();
    ApiKey::factory()->for($admin)->create(['name' => 'older', 'created_at' => now()->subDay()]);
    ApiKey::factory()->for($admin)->revoked()->create(['name' => 'newer']);
    ApiKey::factory()->create(['name' => 'someone elses']);

    $response = $this->actingAs($admin, 'admin')->get(route('admin.api-keys.index'));

    $response->assertOk()
        ->assertSeeInOrder(['newer', 'Revoked', 'older', 'Active'])
        ->assertDontSee('someone elses')
        ->assertDontSee('Your new key');
});

it('says so when the admin has no keys', function (): void {
    $this->actingAs($this->admin(), 'admin')->get(route('admin.api-keys.index'))
        ->assertOk()
        ->assertSee('No keys yet.');
});

it('mints a key, shows the plaintext once on the page that follows, and never again', function (): void {
    $admin = $this->admin();

    $response = $this->actingAs($admin, 'admin')->post(route('admin.api-keys.store'), ['name' => 'laptop']);

    $response->assertRedirect(route('admin.api-keys.index'));

    $key = ApiKey::sole();
    $plaintext = session(ApiKeyController::MINTED_KEY);
    $plaintext = is_string($plaintext) ? $plaintext : '';

    expect($key->admin_id)->toBe($admin->id)
        ->and($key->name)->toBe('laptop')
        ->and(ApiKeyToken::isWellFormed($plaintext))->toBeTrue()
        ->and(ApiKey::forToken($plaintext)->active()->sole()->is($key))->toBeTrue();

    $shown = $this->get(route('admin.api-keys.index'));
    $shown->assertOk()->assertSee('Your new key')->assertSee($plaintext)->assertSee('shown once');

    $this->get(route('admin.api-keys.index'))->assertOk()->assertDontSee($plaintext);
});

it('keeps another admins keys out of the mint form flow', function (): void {
    $this->actingAs($this->admin(), 'admin')->post(route('admin.api-keys.store'), ['name' => 'mine']);

    // A fresh browser: the minting admin's session, flash and all, stays theirs.
    $this->flushSession();
    $other = Admin::factory()->create();

    $this->actingAs($other, 'admin')->get(route('admin.api-keys.index'))
        ->assertOk()
        ->assertDontSee('mine');
});

it('sends a guest to the admin login page', function (): void {
    $this->get(route('admin.api-keys.index'))->assertRedirect(route('auth.admin.login'));
});
