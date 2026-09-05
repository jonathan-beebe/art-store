<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Admin;
use App\Models\ApiKey;

it('lets an admin revoke their own key', function (): void {
    $admin = Admin::factory()->create();
    $key = ApiKey::factory()->for($admin)->create();

    expect((new ApiKeyPolicy)->revoke($admin, $key)->allowed())->toBeTrue();
});

it('answers not-found for another admins key', function (): void {
    $key = ApiKey::factory()->create();
    $other = Admin::factory()->create();

    $response = (new ApiKeyPolicy)->revoke($other, $key);

    expect($response->denied())->toBeTrue()
        ->and($response->status())->toBe(404);
});
