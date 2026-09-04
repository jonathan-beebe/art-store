<?php

declare(strict_types=1);

use App\Domain\Store\StoreVisibility;
use App\Models\Seller;
use App\Models\StoreImage;
use App\Models\StoreLink;
use App\Models\StoreProfile;
use App\Models\StoreSection;
use App\Models\StoreSlug;

it('mints a sto_ id', function (): void {
    expect(StoreProfile::factory()->create()->id)->toStartWith('sto_');
});

it('belongs to the seller it presents', function (): void {
    $seller = Seller::factory()->create();

    $profile = StoreProfile::factory()->create(['seller_id' => $seller->id]);

    expect($profile->seller?->id)->toBe($seller->id)
        ->and($seller->storeProfile?->id)->toBe($profile->id);
});

it('holds one store per seller', function (): void {
    $seller = Seller::factory()->create();
    StoreProfile::factory()->create(['seller_id' => $seller->id]);

    expect(fn () => StoreProfile::factory()->create(['seller_id' => $seller->id]))
        ->toThrow(Illuminate\Database\UniqueConstraintViolationException::class);
});

it('refuses an address another store already answers to', function (): void {
    StoreProfile::factory()->create(['slug' => 'the-burrow-craftworks']);

    expect(fn () => StoreProfile::factory()->create(['slug' => 'the-burrow-craftworks']))
        ->toThrow(Illuminate\Database\UniqueConstraintViolationException::class);
});

it('reads its visibility off the published stamp', function (): void {
    $published = StoreProfile::factory()->create();
    $hidden = StoreProfile::factory()->hidden()->create();

    expect($published->isPublished())->toBeTrue()
        ->and($published->visibility())->toBe(StoreVisibility::Published)
        ->and($hidden->isPublished())->toBeFalse()
        ->and($hidden->visibility())->toBe(StoreVisibility::Hidden);
});

it('lists only the published stores', function (): void {
    $published = StoreProfile::factory()->create();
    StoreProfile::factory()->hidden()->create();

    expect(StoreProfile::query()->published()->pluck('id')->all())->toBe([$published->id]);
});

it('points at its portrait and its cover', function (): void {
    $profile = StoreProfile::factory()->create();
    $portrait = StoreImage::factory()->create(['store_profile_id' => $profile->id]);
    $cover = StoreImage::factory()->create(['store_profile_id' => $profile->id]);

    $profile->update(['portrait_image_id' => $portrait->id, 'cover_image_id' => $cover->id]);

    expect($profile->fresh()?->portraitImage?->id)->toBe($portrait->id)
        ->and($profile->fresh()?->coverImage?->id)->toBe($cover->id);
});

it('orders its sections and its links by position', function (): void {
    $profile = StoreProfile::factory()->create();
    $second = StoreSection::factory()->create(['store_profile_id' => $profile->id, 'position' => 1]);
    $first = StoreSection::factory()->create(['store_profile_id' => $profile->id, 'position' => 0]);
    $secondLink = StoreLink::factory()->instagram()->create(['store_profile_id' => $profile->id]);
    $firstLink = StoreLink::factory()->create(['store_profile_id' => $profile->id]);

    expect($profile->sections()->pluck('id')->all())->toBe([$first->id, $second->id])
        ->and($profile->links()->pluck('id')->all())->toBe([$firstLink->id, $secondLink->id]);
});

it('keeps every address it has answered to', function (): void {
    $profile = StoreProfile::factory()->create();
    StoreSlug::factory()->count(2)->create(['store_profile_id' => $profile->id]);

    expect($profile->slugs()->count())->toBe(2);
});

it('offers the address a buyer opens a published store at', function (): void {
    $profile = StoreProfile::factory()->create(['slug' => 'the-burrow-craftworks']);

    expect($profile->publicUrl())->toBe(route('shop.store', ['slug' => 'the-burrow-craftworks']));
});

it('offers no address while the store is hidden', function (): void {
    expect(StoreProfile::factory()->hidden()->create()->publicUrl())->toBeNull();
});
