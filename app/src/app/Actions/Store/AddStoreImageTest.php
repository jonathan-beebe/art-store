<?php

declare(strict_types=1);

use App\Actions\Store\AddStoreImage;
use App\Domain\Store\StorePictureRole;
use App\Models\StoreImage;
use App\Models\StoreProfile;
use Illuminate\Database\QueryException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\CapturedStory;

beforeEach(function (): void {
    Storage::fake('public');
});

it('puts the file on the public disk and adds it to the store\'s pictures', function (): void {
    $profile = StoreProfile::factory()->create();

    $image = app(AddStoreImage::class)($profile, UploadedFile::fake()->image('studio.jpg'), StorePictureRole::Gallery);

    expect($image)->not->toBeNull();
    assert($image instanceof StoreImage);

    expect($image->store_profile_id)->toBe($profile->id)
        ->and($image->seller_id)->toBe($profile->seller_id)
        ->and($image->path)->toStartWith('stores/')
        ->and(Storage::disk('public')->exists($image->path))->toBeTrue();
});

it('points the profile at a picture uploaded as the portrait or the cover', function (StorePictureRole $role, string $column): void {
    $profile = StoreProfile::factory()->create();

    $image = app(AddStoreImage::class)($profile, UploadedFile::fake()->image('studio.jpg'), $role);

    expect($profile->fresh()?->getAttribute($column))->toBe($image?->id);
})->with([
    'portrait' => [StorePictureRole::Portrait, 'portrait_image_id'],
    'cover' => [StorePictureRole::Cover, 'cover_image_id'],
]);

it('leaves both columns alone for a gallery picture', function (): void {
    $profile = StoreProfile::factory()->create();

    app(AddStoreImage::class)($profile, UploadedFile::fake()->image('studio.jpg'), StorePictureRole::Gallery);

    $saved = $profile->fresh();
    expect($saved?->portrait_image_id)->toBeNull()
        ->and($saved?->cover_image_id)->toBeNull();
});

it('keeps the description the seller typed', function (): void {
    $profile = StoreProfile::factory()->create();

    $image = app(AddStoreImage::class)($profile, UploadedFile::fake()->image('studio.jpg'), StorePictureRole::Gallery, 'The wheel by the window');

    expect($image?->alt)->toBe('The wheel by the window');
});

it('takes the file off the disk again when the row cannot be written', function (): void {
    $profile = StoreProfile::factory()->create();
    // A seller_id no row holds trips the foreign key inside the
    // transaction, the way any failed write there would.
    $profile->seller_id = 'sel_00000000000000000000000000';

    $before = Storage::disk('public')->allFiles('stores');

    expect(fn () => app(AddStoreImage::class)($profile, UploadedFile::fake()->image('studio.jpg'), StorePictureRole::Gallery))
        ->toThrow(QueryException::class);

    expect(Storage::disk('public')->allFiles('stores'))->toBe($before);
});

it('tells the story of the add', function (): void {
    $profile = StoreProfile::factory()->create();
    $log = CapturedStory::capture();

    $image = app(AddStoreImage::class)($profile, UploadedFile::fake()->image('studio.jpg'), StorePictureRole::Gallery);
    assert($image instanceof StoreImage);

    expect($log->values('phase', 'store.image.write'))->toBe(['will', 'did'])
        ->and($log->line('store.image.write', 'did')['data'])->toMatchArray([
            'seller_id' => $profile->seller_id,
            'store_profile_id' => $profile->id,
            'image_id' => $image->id,
            'op' => 'add',
        ]);
});
