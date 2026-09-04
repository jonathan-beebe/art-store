<?php

declare(strict_types=1);

namespace App\Actions\Auth;

use App\Models\Admin;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Auth;

it('signs an existing admin in and verifies the address it signed in with', function (): void {
    $seeded = Admin::factory()->unverified()->create(['email' => 'ops@example.com']);

    $admin = app(SignInAdmin::class)('Ops@Example.com', $this->moment('2026-08-20 09:00:00'));

    expect(Admin::sole()->is($seeded))->toBeTrue()
        ->and($admin->is($seeded))->toBeTrue()
        ->and($admin->email_verified_at?->format('Y-m-d H:i:s'))->toBe('2026-08-20 09:00:00');
});

it('signs a returning admin in without touching an address already verified', function (): void {
    $admin = Admin::factory()->create(['email' => 'ops@example.com']);
    $verifiedAt = $admin->email_verified_at?->format('Y-m-d H:i:s');

    app(SignInAdmin::class)('ops@example.com', $this->moment('2026-08-20 09:00:00'));

    expect(Admin::count())->toBe(1)
        ->and($admin->refresh()->email_verified_at?->format('Y-m-d H:i:s'))->toBe($verifiedAt);
});

it('refuses an address with no admin row and creates none', function (): void {
    $signIn = fn () => app(SignInAdmin::class)('nobody@example.com', $this->moment('2026-08-20 09:00:00'));

    expect($signIn)->toThrow(ModelNotFoundException::class)
        ->and(Admin::count())->toBe(0)
        ->and(Auth::guard('admin')->check())->toBeFalse();
});

it('logs the admin in on the admin guard', function (): void {
    Admin::factory()->create(['email' => 'ops@example.com']);

    $admin = app(SignInAdmin::class)('ops@example.com', $this->moment('2026-08-20 09:00:00'));

    expect(Auth::guard('admin')->id())->toBe($admin->id);
});
