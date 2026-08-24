<?php

declare(strict_types=1);

namespace App\Http\Controllers\Seller;

use App\Actions\Listings\RecordListingEvent;
use App\Actions\Orders\FinalizeOrder;
use App\Domain\Listings\ListingEventType;
use App\Domain\Listings\ListingStatus;
use App\Domain\Reports\DailyActivity;
use App\Models\Listing;
use App\Models\Seller;
use DateTimeImmutable;
use Illuminate\Database\Eloquent\Collection;
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

$recordedActivity = function (Seller $seller): Listing {
    $listing = test()->listing($seller);
    $recordListingEvent = app(RecordListingEvent::class);
    $recordListingEvent($listing, null, ListingEventType::View, test()->moment('2026-08-20 09:00:00'));
    $recordListingEvent($listing, null, ListingEventType::View, test()->moment('2026-08-20 10:00:00'));
    $recordListingEvent($listing, null, ListingEventType::Favorite, test()->moment('2026-08-20 11:00:00'));
    $recordListingEvent($listing, null, ListingEventType::CartAdd, test()->moment('2026-08-20 12:00:00'));

    return $listing;
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

    $response->assertViewHas('listings', function (Collection $listings): bool {
        /** @var Collection<int, Listing> $listings */
        $listing = $listings->sole();

        return $listing->views_count === 2
            && $listing->favorites_count === 0
            && $listing->cart_adds_count === 1;
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
    expect($listing->image_path)->not->toBeNull();
    Storage::disk('public')->assertExists((string) $listing->image_path);
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

it('renders the activity page', function (): void {
    $seller = $this->seller();
    $listing = $this->listing($seller, ['title' => 'Harbour at Dusk']);

    $response = $this->actingAs($seller, 'seller')->get("/seller/listings/{$listing->id}");

    $response->assertOk();
    $response->assertSee('Harbour at Dusk');
});

it('hides another sellers listing from the activity page', function (): void {
    $listing = $this->listing($this->seller('Other Studio'));

    $response = $this->actingAs($this->seller(), 'seller')->get("/seller/listings/{$listing->id}");

    $response->assertNotFound();
});

it('totals the events of the listing', function () use ($recordedActivity): void {
    $seller = $this->seller();
    $listing = $recordedActivity($seller);

    $response = $this->actingAs($seller, 'seller')->get("/seller/listings/{$listing->id}");

    $response->assertViewHas('listing', function (Listing $listing): bool {
        return $listing->views_count === 2
            && $listing->favorites_count === 1
            && $listing->cart_adds_count === 1;
    });
});

it('breaks the last fourteen days down by day', function (): void {
    $seller = $this->seller();
    $listing = $this->listing($seller);

    $response = $this->actingAs($seller, 'seller')->get("/seller/listings/{$listing->id}");

    $response->assertViewHas('days', fn (array $days): bool => count($days) === 14);
    $response->assertViewHas('windowDays', 14);
});

it('counts todays events on todays row', function (): void {
    $seller = $this->seller();
    $listing = $this->listing($seller);
    app(RecordListingEvent::class)($listing, null, ListingEventType::View, new DateTimeImmutable(now()->toDateTimeString()));

    $response = $this->actingAs($seller, 'seller')->get("/seller/listings/{$listing->id}");

    $response->assertViewHas('days', function (array $days): bool {
        $today = $days[13];

        return $today instanceof DailyActivity && $today->views === 1;
    });
});

it('leaves events older than the window off the breakdown', function (): void {
    $seller = $this->seller();
    $listing = $this->listing($seller);
    app(RecordListingEvent::class)($listing, null, ListingEventType::View, $this->moment('2020-01-01 09:00:00'));

    $response = $this->actingAs($seller, 'seller')->get("/seller/listings/{$listing->id}");

    $response->assertViewHas('days', function (array $days): bool {
        /** @var list<DailyActivity> $days */
        return array_sum(array_map(fn (DailyActivity $day): int => $day->total(), $days)) === 0;
    });
});

it('lists the sales of the listing', function (): void {
    $seller = $this->seller();
    $listing = $this->listing($seller, ['title' => 'Harbour at Dusk', 'quantity' => 3]);
    $order = $this->orderFor($this->verifiedCustomer(), $listing);
    app(FinalizeOrder::class)($order, '4242424242424242', $this->moment('2026-08-20 10:00:00'));

    $response = $this->actingAs($seller, 'seller')->get("/seller/listings/{$listing->id}");

    $response->assertViewHas('sales', fn (Collection $sales): bool => $sales->count() === 1);
    $response->assertSee("#{$order->id}");
});

it('renders the activity page on a fixed number of queries however many events the listing recorded', function (): void {
    $seller = $this->seller();
    $listing = $this->listing($seller);
    $recordListingEvent = app(RecordListingEvent::class);
    foreach (range(1, 20) as $hour) {
        $recordListingEvent($listing, null, ListingEventType::View, new DateTimeImmutable(now()->toDateTimeString()));
    }

    $response = $this->actingAs($seller, 'seller')
        ->expectsDatabaseQueryCount(5)
        ->get("/seller/listings/{$listing->id}");

    $response->assertOk();
});

it('renders the edit form with the price in dollars', function (): void {
    $seller = $this->seller();
    $listing = $this->listing($seller, ['title' => 'Harbour at Dusk', 'price_cents' => 24900]);

    $response = $this->actingAs($seller, 'seller')->get("/seller/listings/{$listing->id}/edit");

    $response->assertOk();
    $response->assertSee('value="249.00"', escape: false);
});

it('renders the edit form as a PUT form', function (): void {
    $seller = $this->seller();
    $listing = $this->listing($seller);

    $response = $this->actingAs($seller, 'seller')->get("/seller/listings/{$listing->id}/edit");

    $response->assertSee('<input type="hidden" name="_method" value="PUT">', escape: false);
});

it('updates a listing from the form', function () use ($form): void {
    $seller = $this->seller();
    $listing = $this->listing($seller, ['title' => 'Old title']);

    $response = $this->actingAs($seller, 'seller')->put("/seller/listings/{$listing->id}", $form());

    $response->assertRedirect(route('seller.listings.index'));
    $listing->refresh();
    expect($listing->title)->toBe('Harbour at Dusk')
        ->and($listing->price_cents)->toBe(24900);
});

it('rejects an update without a title', function () use ($form): void {
    $seller = $this->seller();
    $listing = $this->listing($seller, ['title' => 'Old title']);

    $response = $this->actingAs($seller, 'seller')
        ->put("/seller/listings/{$listing->id}", $form(['title' => '']));

    $response->assertSessionHasErrors('title');
    expect($listing->refresh()->title)->toBe('Old title');
});

it('keeps the previous image when a replacement upload fails', function () use ($form): void {
    $seller = $this->seller();
    $listing = $this->listing($seller, ['image_path' => 'listings/old.jpg']);
    Storage::shouldReceive('disk')->with('public')->andReturnSelf();
    Storage::shouldReceive('putFile')->andReturn(false);

    $this->actingAs($seller, 'seller')->put("/seller/listings/{$listing->id}", $form([
        'image' => UploadedFile::fake()->image('harbour.jpg'),
    ]));

    expect($listing->refresh()->image_path)->toBe('listings/old.jpg');
});

it('hides another sellers listing from the edit form', function (): void {
    $listing = $this->listing($this->seller('Other Studio'));

    $response = $this->actingAs($this->seller(), 'seller')->get("/seller/listings/{$listing->id}/edit");

    $response->assertNotFound();
});

it('refuses to update another sellers listing', function () use ($form): void {
    $listing = $this->listing($this->seller('Other Studio'), ['title' => 'Not Mine']);

    $response = $this->actingAs($this->seller(), 'seller')->put("/seller/listings/{$listing->id}", $form());

    $response->assertNotFound();
    expect($listing->refresh()->title)->toBe('Not Mine');
});
