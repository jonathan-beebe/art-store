<?php

declare(strict_types=1);

namespace App\Http\Requests\Seller;

use App\Domain\Store\StorePictureRole;
use App\Models\StoreImage;
use App\Models\StoreProfile;
use Illuminate\Http\UploadedFile;

it('mints the store on a first POST, before any GET /seller/store', function (): void {
    $seller = $this->seller('The Burrow Craftworks');

    $response = $this->actingAs($seller, 'seller')->post('/seller/store/images', [
        'image' => UploadedFile::fake()->image('portrait.jpg'),
        'role' => StorePictureRole::Portrait->value,
    ]);

    $response->assertRedirect(route('seller.store.show'));
    expect($seller->storeProfile()->exists())->toBeTrue();
});

it('refuses a file type other than jpeg, png, webp, or gif', function (): void {
    $seller = $this->seller('The Burrow Craftworks');
    $this->actingAs($seller, 'seller')->get('/seller/store');

    $response = $this->actingAs($seller, 'seller')->post('/seller/store/images', [
        'image' => UploadedFile::fake()->create('notes.txt', 10),
        'role' => StorePictureRole::Gallery->value,
    ]);

    $response->assertSessionHasErrors('image');
});

it('refuses a file over the 5120 KB limit', function (): void {
    $seller = $this->seller('The Burrow Craftworks');
    $this->actingAs($seller, 'seller')->get('/seller/store');

    $response = $this->actingAs($seller, 'seller')->post('/seller/store/images', [
        'image' => UploadedFile::fake()->image('huge.jpg')->size(5121),
        'role' => StorePictureRole::Gallery->value,
    ]);

    $response->assertSessionHasErrors('image');
});

it('requires a role', function (): void {
    $seller = $this->seller('The Burrow Craftworks');
    $this->actingAs($seller, 'seller')->get('/seller/store');

    $response = $this->actingAs($seller, 'seller')->post('/seller/store/images', [
        'image' => UploadedFile::fake()->image('portrait.jpg'),
    ]);

    $response->assertSessionHasErrors('role');
});

it('refuses a role outside portrait, cover, or gallery', function (): void {
    $seller = $this->seller('The Burrow Craftworks');
    $this->actingAs($seller, 'seller')->get('/seller/store');

    $response = $this->actingAs($seller, 'seller')->post('/seller/store/images', [
        'image' => UploadedFile::fake()->image('portrait.jpg'),
        'role' => 'thumbnail',
    ]);

    $response->assertSessionHasErrors('role');
});

it('refuses a picture past the store cap with its cap message', function (): void {
    $seller = $this->seller('The Burrow Craftworks');
    $this->actingAs($seller, 'seller')->get('/seller/store');
    $profile = $seller->storeProfile()->sole();
    StoreImage::factory()
        ->count(StoreProfile::MAX_IMAGES)
        ->create(['store_profile_id' => $profile->id, 'seller_id' => $seller->id]);

    $response = $this->actingAs($seller, 'seller')->post('/seller/store/images', [
        'image' => UploadedFile::fake()->image('one-too-many.jpg'),
        'role' => StorePictureRole::Gallery->value,
    ]);

    $response->assertSessionHasErrors([
        'image' => 'This store already holds '.StoreProfile::MAX_IMAGES.' pictures, the most allowed.',
    ]);
    expect(StoreImage::where('store_profile_id', $profile->id)->count())->toBe(StoreProfile::MAX_IMAGES);
});
