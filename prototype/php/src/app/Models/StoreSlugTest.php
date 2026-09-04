<?php

declare(strict_types=1);

use App\Models\StoreProfile;
use App\Models\StoreSlug;

it('mints an ssl_ id', function (): void {
    expect(StoreSlug::factory()->create()->id)->toStartWith('ssl_');
});

it('belongs to the store it names', function (): void {
    $profile = StoreProfile::factory()->create();

    $slug = StoreSlug::factory()->create(['store_profile_id' => $profile->id]);

    expect($slug->storeProfile?->id)->toBe($profile->id);
});

it('refuses an address any store already holds, current or retired', function (): void {
    StoreSlug::factory()->retired()->create(['slug' => 'the-burrow-craftworks']);

    expect(fn () => StoreSlug::factory()->create(['slug' => 'the-burrow-craftworks']))
        ->toThrow(Illuminate\Database\UniqueConstraintViolationException::class);
});

it('reads whether it has been left behind', function (): void {
    expect(StoreSlug::factory()->create()->isRetired())->toBeFalse()
        ->and(StoreSlug::factory()->retired()->create()->isRetired())->toBeTrue();
});

it('separates the current address from the retired ones', function (): void {
    $profile = StoreProfile::factory()->create();
    $current = StoreSlug::factory()->create(['store_profile_id' => $profile->id]);
    $retired = StoreSlug::factory()->retired()->create(['store_profile_id' => $profile->id]);

    expect(StoreSlug::query()->current()->pluck('id')->all())->toBe([$current->id])
        ->and(StoreSlug::query()->retired()->pluck('id')->all())->toBe([$retired->id]);
});
