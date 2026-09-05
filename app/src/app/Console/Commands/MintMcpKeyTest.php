<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Admin;
use App\Models\ApiKey;
use Illuminate\Testing\PendingCommand;
use RuntimeException;

/**
 * `$this->artisan()` hands back an exit code when console output is not
 * mocked and a pending command when it is; these tests assert on output.
 */
$pending = fn (PendingCommand|int $command): PendingCommand => $command instanceof PendingCommand
    ? $command
    : throw new RuntimeException('Console output is not mocked, so the command ran instead of pending.');

it('mints a key for the admin at the address and prints it once', function () use ($pending): void {
    $admin = Admin::factory()->create(['email' => 'anna@example.com']);

    $pending($this->artisan('mcp:key', ['email' => 'Anna@Example.com', '--name' => 'laptop']))
        ->expectsOutputToContain('The key is shown once:')
        ->expectsOutputToContain('artstore_')
        ->assertSuccessful();

    $key = ApiKey::sole();

    expect($key->admin_id)->toBe($admin->id)
        ->and($key->name)->toBe('laptop');
});

it('refuses an address no admin has, minting nothing', function () use ($pending): void {
    $pending($this->artisan('mcp:key', ['email' => 'nobody@example.com']))
        ->expectsOutputToContain('No admin has the address nobody@example.com.')
        ->assertFailed();

    expect(ApiKey::count())->toBe(0);
});

it('refuses an empty name', function () use ($pending): void {
    Admin::factory()->create(['email' => 'anna@example.com']);

    $pending($this->artisan('mcp:key', ['email' => 'anna@example.com', '--name' => '']))
        ->assertFailed();

    expect(ApiKey::count())->toBe(0);
});
