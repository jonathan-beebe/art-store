<?php

declare(strict_types=1);

use App\Actions\Store\RenameStoreSlug;
use App\Actions\Store\StartStore;
use App\Models\Seller;
use App\Models\StoreProfile;
use App\Seller\Store\StoreAddressLookup;

it('finds the store answering to an address today', function (): void {
    $profile = StoreProfile::factory()->create(['slug' => 'the-burrow-craftworks']);

    expect((new StoreAddressLookup)->current('the-burrow-craftworks')?->id)->toBe($profile->id);
});

it('finds nothing for an address no store holds', function (): void {
    StoreProfile::factory()->create(['slug' => 'the-burrow-craftworks']);

    expect((new StoreAddressLookup)->current('nine-owls'))->toBeNull();
});

it('names the address a store moved to', function (): void {
    $profile = app(StartStore::class)(Seller::factory()->create(['shop_name' => 'The Burrow Craftworks']));
    $profile->update(['published_at' => now()]);
    app(RenameStoreSlug::class)($profile, 'burrow-works', new DateTimeImmutable('2026-09-01 10:00:00'));

    $movedTo = (new StoreAddressLookup)->movedTo('the-burrow-craftworks', new DateTimeImmutable('2026-09-10 10:00:00'));

    expect($movedTo)->toBe('burrow-works');
});

it('stops naming it once the window has closed', function (): void {
    $profile = app(StartStore::class)(Seller::factory()->create(['shop_name' => 'The Burrow Craftworks']));
    $profile->update(['published_at' => now()]);
    app(RenameStoreSlug::class)($profile, 'burrow-works', new DateTimeImmutable('2026-09-01 10:00:00'));

    $movedTo = (new StoreAddressLookup)->movedTo('the-burrow-craftworks', new DateTimeImmutable('2026-10-05 10:00:00'));

    expect($movedTo)->toBeNull();
});

it('names nothing for an address that is still current', function (): void {
    StoreProfile::factory()->create(['slug' => 'the-burrow-craftworks']);

    expect((new StoreAddressLookup)->movedTo('the-burrow-craftworks', new DateTimeImmutable('2026-09-10 10:00:00')))->toBeNull();
});

it('names nothing for an address no store has ever held', function (): void {
    expect((new StoreAddressLookup)->movedTo('nine-owls', new DateTimeImmutable('2026-09-10 10:00:00')))->toBeNull();
});

it('names nothing for an address whose store is now hidden', function (): void {
    $profile = app(StartStore::class)(Seller::factory()->create(['shop_name' => 'The Burrow Craftworks']));
    $profile->update(['published_at' => now()]);
    app(RenameStoreSlug::class)($profile, 'burrow-works', new DateTimeImmutable('2026-09-01 10:00:00'));
    $profile->update(['published_at' => null]);

    $movedTo = (new StoreAddressLookup)->movedTo('the-burrow-craftworks', new DateTimeImmutable('2026-09-10 10:00:00'));

    expect($movedTo)->toBeNull();
});
