<?php

declare(strict_types=1);

namespace App\Http\Controllers\Seller;

use App\Actions\Listings\RecordListingEvent;
use App\Domain\Listings\ListingEventType;
use App\Domain\Listings\ListingStatus;
use App\Models\Listing;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
$form = function (array $overrides = []): array {
    return $overrides + [
        'title' => 'Harbour at Dusk',
        'description' => 'Oil on linen.',
        'medium' => 'oil',
        'dimensions' => '12 x 16 in',
        'price' => '249.00',
        'quantity' => 1,
    ];
};

it('lists the sellers listings', function (): void {
    $seller = $this->seller();
    $this->listing($seller, ['title' => 'Harbour at Dusk']);

    $response = $this->actingAs($seller, 'seller')->get('/seller/listings');

    $response->assertOk();
    $response->assertSee('Harbour at Dusk');
});

it('keeps another sellers listings off the table', function (): void {
    $this->listing($this->seller('Other Studio'), ['title' => 'Not Mine']);

    $response = $this->actingAs($this->seller(), 'seller')->get('/seller/listings');

    $response->assertDontSee('Not Mine');
});

it('shows the event counts for each listing', function (): void {
    $seller = $this->seller();
    $listing = $this->listing($seller, ['title' => 'Harbour at Dusk']);
    $recordListingEvent = app(RecordListingEvent::class);
    $recordListingEvent($listing, null, ListingEventType::View, $this->moment('2026-08-20 09:00:00'));
    $recordListingEvent($listing, null, ListingEventType::View, $this->moment('2026-08-20 10:00:00'));
    $recordListingEvent($listing, null, ListingEventType::CartAdd, $this->moment('2026-08-20 11:00:00'));

    $response = $this->actingAs($seller, 'seller')->get('/seller/listings');

    $response->assertViewHas('listings', function ($listings): bool {
        return $listings->first()->views_count === 2
            && $listings->first()->favorites_count === 0
            && $listings->first()->cart_adds_count === 1;
    });
});

it('shows a placeholder thumbnail for a listing without an image', function (): void {
    $seller = $this->seller();
    $listing = $this->listing($seller, ['image_path' => null]);

    $response = $this->actingAs($seller, 'seller')->get('/seller/listings');

    $response->assertSee($listing->imageUrl(), escape: false);
});

it('renders the create form', function (): void {
    $response = $this->actingAs($this->seller(), 'seller')->get('/seller/listings/create');

    $response->assertOk();
    $response->assertSee('New listing');
    $response->assertSee('for="price"', escape: false);
});

it('creates a listing from the form', function () use ($form): void {
    $seller = $this->seller();

    $response = $this->actingAs($seller, 'seller')->post('/seller/listings', $form());

    $response->assertRedirect(route('seller.listings.index'));
    $listing = Listing::where('seller_id', $seller->id)->sole();
    expect($listing->title)->toBe('Harbour at Dusk')
        ->and($listing->price_cents)->toBe(24900)
        ->and($listing->status)->toBe(ListingStatus::Draft);
});

it('stores an uploaded image on the public disk', function () use ($form): void {
    Storage::fake('public');
    $seller = $this->seller();

    $this->actingAs($seller, 'seller')->post('/seller/listings', $form([
        'image' => UploadedFile::fake()->image('harbour.jpg'),
    ]));

    $listing = Listing::where('seller_id', $seller->id)->sole();
    Storage::disk('public')->assertExists($listing->image_path);
});

it('creates the listing without an image and tells the seller when the upload fails', function () use ($form): void {
    Storage::shouldReceive('disk')->with('public')->andReturnSelf();
    Storage::shouldReceive('putFile')->andReturn(false);
    $seller = $this->seller();

    $response = $this->actingAs($seller, 'seller')->post('/seller/listings', $form([
        'image' => UploadedFile::fake()->image('harbour.jpg'),
    ]));

    $response->assertSessionHas('status', fn (string $status): bool => str_contains($status, 'image failed to upload'));
    $listing = Listing::where('seller_id', $seller->id)->sole();
    expect($listing->image_path)->toBeNull();
});

it('rejects invalid listing input', function (array $overrides, string $field) use ($form): void {
    $response = $this->actingAs($this->seller(), 'seller')
        ->post('/seller/listings', $form($overrides));

    $response->assertSessionHasErrors($field);
    expect(Listing::count())->toBe(0);
})->with([
    'a listing without a title' => [['title' => ''], 'title'],
    'a price that is not an amount in dollars' => [['price' => 'a lot'], 'price'],
    'a price carrying fractions of a cent' => [['price' => '249.999'], 'price'],
    'a negative quantity' => [['quantity' => -1], 'quantity'],
]);

it('rejects an invalid image upload', function (string $filename, int $kilobytes, string $mimeType) use ($form): void {
    Storage::fake('public');

    $response = $this->actingAs($this->seller(), 'seller')->post('/seller/listings', $form([
        'image' => UploadedFile::fake()->create($filename, $kilobytes, $mimeType),
    ]));

    $response->assertSessionHasErrors('image');
    expect(Listing::count())->toBe(0);
})->with([
    'a file that is not an image at all' => ['notes.txt', 4, 'text/plain'],
    'a file that only claims to be an image' => ['harbour.jpg', 12, 'image/jpeg'],
]);

it('renders the edit form with the price in dollars', function (): void {
    $seller = $this->seller();
    $listing = $this->listing($seller, ['title' => 'Harbour at Dusk', 'price_cents' => 24900]);

    $response = $this->actingAs($seller, 'seller')->get("/seller/listings/{$listing->id}/edit");

    $response->assertOk();
    $response->assertSee('value="249.00"', escape: false);
});

it('updates a listing from the form', function () use ($form): void {
    $seller = $this->seller();
    $listing = $this->listing($seller, ['title' => 'Old title']);

    $response = $this->actingAs($seller, 'seller')->post("/seller/listings/{$listing->id}", $form());

    $response->assertRedirect(route('seller.listings.index'));
    expect($listing->fresh()->title)->toBe('Harbour at Dusk')
        ->and($listing->fresh()->price_cents)->toBe(24900);
});

it('rejects an update without a title', function () use ($form): void {
    $seller = $this->seller();
    $listing = $this->listing($seller, ['title' => 'Old title']);

    $response = $this->actingAs($seller, 'seller')
        ->post("/seller/listings/{$listing->id}", $form(['title' => '']));

    $response->assertSessionHasErrors('title');
    expect($listing->fresh()->title)->toBe('Old title');
});

it('keeps the previous image when a replacement upload fails', function () use ($form): void {
    $seller = $this->seller();
    $listing = $this->listing($seller, ['image_path' => 'listings/old.jpg']);
    Storage::shouldReceive('disk')->with('public')->andReturnSelf();
    Storage::shouldReceive('putFile')->andReturn(false);

    $this->actingAs($seller, 'seller')->post("/seller/listings/{$listing->id}", $form([
        'image' => UploadedFile::fake()->image('harbour.jpg'),
    ]));

    expect($listing->fresh()->image_path)->toBe('listings/old.jpg');
});

it('hides another sellers listing from the edit form', function (): void {
    $listing = $this->listing($this->seller('Other Studio'));

    $response = $this->actingAs($this->seller(), 'seller')->get("/seller/listings/{$listing->id}/edit");

    $response->assertNotFound();
});

it('refuses to update another sellers listing', function () use ($form): void {
    $listing = $this->listing($this->seller('Other Studio'), ['title' => 'Not Mine']);

    $response = $this->actingAs($this->seller(), 'seller')->post("/seller/listings/{$listing->id}", $form());

    $response->assertNotFound();
    expect($listing->fresh()->title)->toBe('Not Mine');
});
