<?php

declare(strict_types=1);

namespace App\Http\Requests\Seller;

use App\Domain\Store\StoreSectionKind;
use App\Models\StoreImage;
use App\Models\StoreProfile;
use App\Models\StoreSection;

it('requires a valid kind for a new section', function (array $overrides): void {
    $seller = $this->seller('The Burrow Craftworks');
    $this->actingAs($seller, 'seller')->get('/seller/store');

    $response = $this->actingAs($seller, 'seller')->post('/seller/store/sections', $overrides);

    $response->assertSessionHasErrors('kind');
})->with([
    'no kind at all' => [[]],
    'an unrecognized kind' => [['kind' => 'faq']],
]);

it('accepts a story section with a heading and a body', function (): void {
    $seller = $this->seller('The Burrow Craftworks');
    $this->actingAs($seller, 'seller')->get('/seller/store');

    $response = $this->actingAs($seller, 'seller')->post('/seller/store/sections', [
        'kind' => StoreSectionKind::Story->value,
        'heading' => 'My story',
        'body' => 'Made by hand, in the shed out back.',
    ]);

    $response->assertSessionHasNoErrors();
});

it('refuses images on a story section', function (): void {
    $seller = $this->seller('The Burrow Craftworks');
    $this->actingAs($seller, 'seller')->get('/seller/store');
    $profile = $seller->storeProfile()->sole();
    $image = StoreImage::factory()->create(['store_profile_id' => $profile->id, 'seller_id' => $seller->id]);

    $response = $this->actingAs($seller, 'seller')->post('/seller/store/sections', [
        'kind' => StoreSectionKind::Story->value,
        'heading' => 'My story',
        'images' => [$image->id],
    ]);

    $response->assertSessionHasErrors('images');
});

it('accepts a gallery section with a heading and images', function (): void {
    $seller = $this->seller('The Burrow Craftworks');
    $this->actingAs($seller, 'seller')->get('/seller/store');
    $profile = $seller->storeProfile()->sole();
    $image = StoreImage::factory()->create(['store_profile_id' => $profile->id, 'seller_id' => $seller->id]);

    $response = $this->actingAs($seller, 'seller')->post('/seller/store/sections', [
        'kind' => StoreSectionKind::Gallery->value,
        'heading' => 'The studio',
        'images' => [$image->id],
    ]);

    $response->assertSessionHasNoErrors();
});

it('refuses a body on a gallery section', function (): void {
    $seller = $this->seller('The Burrow Craftworks');
    $this->actingAs($seller, 'seller')->get('/seller/store');

    $response = $this->actingAs($seller, 'seller')->post('/seller/store/sections', [
        'kind' => StoreSectionKind::Gallery->value,
        'heading' => 'The studio',
        'body' => 'Not allowed here.',
    ]);

    $response->assertSessionHasErrors('body');
});

it('refuses a body past the length ceiling', function (): void {
    $seller = $this->seller('The Burrow Craftworks');
    $this->actingAs($seller, 'seller')->get('/seller/store');

    $response = $this->actingAs($seller, 'seller')->post('/seller/store/sections', [
        'kind' => StoreSectionKind::Story->value,
        'body' => str_repeat('a', StoreSection::MAX_BODY_LENGTH + 1),
    ]);

    $response->assertSessionHasErrors('body');
});

it('refuses more images than a gallery holds', function (): void {
    $seller = $this->seller('The Burrow Craftworks');
    $this->actingAs($seller, 'seller')->get('/seller/store');
    $profile = $seller->storeProfile()->sole();
    $images = StoreImage::factory()
        ->count(StoreSection::MAX_GALLERY_IMAGES + 1)
        ->create(['store_profile_id' => $profile->id, 'seller_id' => $seller->id]);

    $response = $this->actingAs($seller, 'seller')->post('/seller/store/sections', [
        'kind' => StoreSectionKind::Gallery->value,
        'images' => $images->pluck('id')->all(),
    ]);

    $response->assertSessionHasErrors('images');
});

it('refuses an image id belonging to another stores profile', function (): void {
    $seller = $this->seller('The Burrow Craftworks');
    $this->actingAs($seller, 'seller')->get('/seller/store');
    $foreignImage = StoreImage::factory()->create();

    $response = $this->actingAs($seller, 'seller')->post('/seller/store/sections', [
        'kind' => StoreSectionKind::Gallery->value,
        'images' => [$foreignImage->id],
    ]);

    $response->assertSessionHasErrors('images.0');
});

it('refuses a thirteenth section with its cap message', function (): void {
    $seller = $this->seller('The Burrow Craftworks');
    $this->actingAs($seller, 'seller')->get('/seller/store');
    $profile = $seller->storeProfile()->sole();
    for ($position = 0; $position < StoreSection::MAX_PER_PROFILE; $position++) {
        StoreSection::factory()->create(['store_profile_id' => $profile->id, 'position' => $position]);
    }

    $response = $this->actingAs($seller, 'seller')->post('/seller/store/sections', [
        'kind' => StoreSectionKind::Story->value,
    ]);

    $response->assertSessionHasErrors([
        'kind' => 'This store page already holds '.StoreSection::MAX_PER_PROFILE.' sections, the most allowed.',
    ]);
    expect(StoreSection::where('store_profile_id', $profile->id)->count())->toBe(StoreSection::MAX_PER_PROFILE);
});

it('answers another sellers section as not found', function (): void {
    $seller = $this->seller('The Burrow Craftworks');
    $this->actingAs($seller, 'seller')->get('/seller/store');
    $otherProfile = StoreProfile::factory()->create();
    $otherSection = StoreSection::factory()->create(['store_profile_id' => $otherProfile->id]);

    $response = $this->actingAs($seller, 'seller')->put("/seller/store/sections/{$otherSection->id}", [
        'heading' => 'Nope',
    ]);

    $response->assertNotFound();
});
