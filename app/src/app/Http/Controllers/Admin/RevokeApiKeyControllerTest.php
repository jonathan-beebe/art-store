<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Models\Admin;
use App\Models\ApiKey;

it('revokes the admins own key and says so', function (): void {
    $admin = $this->admin();
    $key = ApiKey::factory()->for($admin)->create(['name' => 'laptop']);

    $response = $this->actingAs($admin, 'admin')->post(route('admin.api-keys.revoke', $key));

    $response->assertRedirect(route('admin.api-keys.index'))->assertSessionHas('status', 'Key laptop revoked.');
    expect($key->refresh()->isRevoked())->toBeTrue();
});

it('answers 404 for another admins key, leaving it active', function (): void {
    $key = ApiKey::factory()->create();

    $this->actingAs(Admin::factory()->create(), 'admin')->post(route('admin.api-keys.revoke', $key))
        ->assertNotFound();

    expect($key->refresh()->isRevoked())->toBeFalse();
});

it('answers 404 for an id of the wrong shape or no row', function (): void {
    $this->actingAs($this->admin(), 'admin')->post('/admin/settings/api-keys/key_00000000000000000000000000/revoke')
        ->assertNotFound();
});
