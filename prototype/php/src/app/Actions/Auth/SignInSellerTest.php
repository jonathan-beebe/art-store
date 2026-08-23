<?php

declare(strict_types=1);

namespace App\Actions\Auth;

use App\Models\Seller;
use Illuminate\Support\Facades\Auth;

it('creates a first-time seller and verifies the address it signed in with', function (): void {
    $seller = app(SignInSeller::class)('Artist@Example.com', $this->moment('2026-08-20 09:00:00'));

    expect(Seller::sole()->is($seller))->toBeTrue()
        ->and($seller->email)->toBe('artist@example.com')
        ->and($seller->email_verified_at?->format('Y-m-d H:i:s'))->toBe('2026-08-20 09:00:00');
});

it('signs a returning seller in without touching an address already verified', function (): void {
    $seller = Seller::factory()->create(['email' => 'artist@example.com']);
    $verifiedAt = $seller->email_verified_at?->format('Y-m-d H:i:s');

    app(SignInSeller::class)('artist@example.com', $this->moment('2026-08-20 09:00:00'));

    expect(Seller::count())->toBe(1)
        ->and($seller->fresh()->email_verified_at?->format('Y-m-d H:i:s'))->toBe($verifiedAt);
});

it('logs the seller in on the seller guard', function (): void {
    $seller = app(SignInSeller::class)('artist@example.com', $this->moment('2026-08-20 09:00:00'));

    expect(Auth::guard('seller')->id())->toBe($seller->id);
});
