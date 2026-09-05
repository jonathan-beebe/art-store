<?php

declare(strict_types=1);

use App\Actions\Store\RenameStoreSlug;
use App\Actions\Store\StartStore;
use App\Models\Seller;
use Tests\CapturedStory;

it('moves the store to the new address', function (): void {
    $profile = app(StartStore::class)(Seller::factory()->create(['shop_name' => 'The Burrow Craftworks']));

    app(RenameStoreSlug::class)($profile, 'burrow-works', new DateTimeImmutable('2026-09-03 10:00:00'));

    expect($profile->fresh()?->slug)->toBe('burrow-works');
});

it('keeps the old address with the day it was retired', function (): void {
    $profile = app(StartStore::class)(Seller::factory()->create(['shop_name' => 'The Burrow Craftworks']));

    app(RenameStoreSlug::class)($profile, 'burrow-works', new DateTimeImmutable('2026-09-03 10:00:00'));

    $retired = $profile->slugs()->retired()->sole();
    expect($retired->slug)->toBe('the-burrow-craftworks')
        ->and($retired->retired_at?->toDateTimeString())->toBe('2026-09-03 10:00:00');
});

it('leaves exactly one current address behind', function (): void {
    $profile = app(StartStore::class)(Seller::factory()->create(['shop_name' => 'The Burrow Craftworks']));

    app(RenameStoreSlug::class)($profile, 'burrow-works', new DateTimeImmutable('2026-09-03 10:00:00'));

    expect($profile->slugs()->current()->pluck('slug')->all())->toBe(['burrow-works'])
        ->and($profile->slugs()->count())->toBe(2);
});

it('writes nothing when the address has not changed', function (): void {
    $profile = app(StartStore::class)(Seller::factory()->create(['shop_name' => 'The Burrow Craftworks']));

    app(RenameStoreSlug::class)($profile, 'the-burrow-craftworks', new DateTimeImmutable('2026-09-03 10:00:00'));

    expect($profile->slugs()->count())->toBe(1)
        ->and($profile->slugs()->sole()->isRetired())->toBeFalse();
});

it('brings an address the store retired earlier back as the current one', function (): void {
    $profile = app(StartStore::class)(Seller::factory()->create(['shop_name' => 'The Burrow Craftworks']));
    app(RenameStoreSlug::class)($profile, 'burrow-works', new DateTimeImmutable('2026-09-03 10:00:00'));

    app(RenameStoreSlug::class)($profile->fresh() ?? $profile, 'the-burrow-craftworks', new DateTimeImmutable('2026-09-04 10:00:00'));

    expect($profile->fresh()?->slug)->toBe('the-burrow-craftworks')
        ->and($profile->slugs()->current()->pluck('slug')->all())->toBe(['the-burrow-craftworks'])
        ->and($profile->slugs()->count())->toBe(2);
});

it('tells the story of the rename, and no story at all when the address holds', function (): void {
    $profile = app(StartStore::class)(Seller::factory()->create(['shop_name' => 'The Burrow Craftworks']));
    $log = CapturedStory::capture();

    app(RenameStoreSlug::class)($profile, 'the-burrow-craftworks', new DateTimeImmutable('2026-09-03 10:00:00'));
    expect($log->lines())->toBe([]);

    app(RenameStoreSlug::class)($profile, 'burrow-works', new DateTimeImmutable('2026-09-03 10:00:00'));

    expect($log->values('phase', 'store.slug.rename'))->toBe(['will', 'did'])
        ->and($log->line('store.slug.rename', 'did')['data'])->toMatchArray([
            'seller_id' => $profile->seller_id,
            'store_profile_id' => $profile->id,
            'slug_from' => 'the-burrow-craftworks',
            'slug_to' => 'burrow-works',
        ]);
});
