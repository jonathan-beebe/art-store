<?php

declare(strict_types=1);

use App\Actions\Store\StartStore;
use App\Domain\Store\StorePictureRole;
use App\Models\Seller;
use App\Models\StoreImage;
use App\Models\StoreProfile;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * A signed-in seller and the store their first visit would mint, with the
 * public disk faked so an upload writes nowhere real.
 *
 * @return array{Seller, StoreProfile}
 */
$storekeeper = function (): array {
    Storage::fake('public');
    $seller = Seller::factory()->create(['shop_name' => 'The Burrow Craftworks']);

    return [$seller, app(StartStore::class)($seller)];
};

it('adds a picture to the store', function () use ($storekeeper): void {
    [$seller, $profile] = $storekeeper();

    $this->actingAs($seller, 'seller')
        ->post('/seller/store/images', [
            'image' => UploadedFile::fake()->image('studio.jpg'),
            'role' => StorePictureRole::Gallery->value,
        ])
        ->assertRedirect(route('seller.store.show'));

    expect($profile->images()->count())->toBe(1);
});

it('points the store at a new portrait', function () use ($storekeeper): void {
    [$seller, $profile] = $storekeeper();

    $this->actingAs($seller, 'seller')->post('/seller/store/images', [
        'image' => UploadedFile::fake()->image('me.jpg'),
        'role' => StorePictureRole::Portrait->value,
    ]);

    expect($profile->fresh()?->portrait_image_id)->toBe($profile->images()->sole()->id);
});

it('takes a picture out of the store', function () use ($storekeeper): void {
    [$seller, $profile] = $storekeeper();
    $image = StoreImage::factory()->create(['store_profile_id' => $profile->id, 'seller_id' => $seller->id]);

    $this->actingAs($seller, 'seller')
        ->delete("/seller/store/images/{$image->id}")
        ->assertRedirect(route('seller.store.show'));

    expect(StoreImage::find($image->id))->toBeNull();
});

it('answers not found for another store\'s picture', function () use ($storekeeper): void {
    [$seller] = $storekeeper();
    $image = StoreImage::factory()->create(['store_profile_id' => StoreProfile::factory()->create()->id]);

    $this->actingAs($seller, 'seller')
        ->delete("/seller/store/images/{$image->id}")
        ->assertNotFound();

    expect(StoreImage::find($image->id))->not->toBeNull();
});

it('sends a signed-out visitor to the sign-in page', function (): void {
    $this->post('/seller/store/images')->assertRedirect(route('auth.seller.login'));
});

it('keeps the description a seller typed with the picture', function () use ($storekeeper): void {
    [$seller, $profile] = $storekeeper();

    $this->actingAs($seller, 'seller')->post('/seller/store/images', [
        'image' => UploadedFile::fake()->image('studio.jpg'),
        'role' => StorePictureRole::Gallery->value,
        'alt' => 'The wheel by the window',
    ]);

    expect($profile->images()->sole()->alt)->toBe('The wheel by the window');
});

it('offers a description field on the upload form', function () use ($storekeeper): void {
    [$seller] = $storekeeper();

    $this->actingAs($seller, 'seller')
        ->get('/seller/store')
        ->assertOk()
        ->assertSee('name="alt"', false);
});
