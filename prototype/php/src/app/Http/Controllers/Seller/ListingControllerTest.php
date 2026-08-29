<?php

declare(strict_types=1);

namespace App\Http\Controllers\Seller;

use App\Actions\Listings\RecordListingEvent;
use App\Actions\Orders\FinalizeOrder;
use App\Domain\Listings\ListingEventType;
use App\Domain\Listings\ListingStatus;
use App\Domain\RateLimiting\RateLimitValue;
use App\Domain\Reports\DailyActivity;
use App\Models\Category;
use App\Models\CategoryProperty;
use App\Models\DescriptionSection;
use App\Models\Listing;
use App\Models\ListingAttribute;
use App\Models\ListingRemoval;
use App\Models\Modifier;
use App\Models\OptionAxis;
use App\Models\OptionValue;
use App\Models\Property;
use App\Models\PropertyValue;
use App\Models\QuantityBreak;
use App\Models\Seller;
use App\Models\Variant;
use DateTimeImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\Sequence;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Config;

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
$form = function (array $overrides = []): array {
    return $overrides + [
        'shape' => 'one',
        'title' => 'Harbour at Dusk',
        'description' => 'Oil on linen.',
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
    $listing = $this->listing($seller);

    $response = $this->actingAs($seller, 'seller')->get('/seller/listings');

    $response->assertSee($listing->imageUrl(), escape: false);
});

it('DSGN-003 renders the create question screen with the three pricing shapes', function (): void {
    $response = $this->actingAs($this->seller(), 'seller')->get('/seller/listings/create');

    $response->assertOk();
    $response->assertSee('New listing');
    $response->assertSee('What are you selling?');
    $response->assertSee('One thing, one price');
    $response->assertSee('It comes in versions, each with its own price');
    $response->assertSee('One price, with extras that add to it');
    $response->assertSee('This just picks your starting point');
});

it('DSGN-003 continues to the one-thing landing screen with the title carried over', function (): void {
    $response = $this->actingAs($this->seller(), 'seller')
        ->get('/seller/listings/create?'.http_build_query(['title' => 'Harbour at Dusk', 'shape' => 'one']));

    $response->assertOk();
    $response->assertSee('Harbour at Dusk');
    $response->assertSee('value="Harbour at Dusk"', escape: false);
    $response->assertSee('for="price"', escape: false);
    $response->assertSee('Made to order — no fixed count');
});

it('DSGN-003 continues to the versions landing screen with no price field', function (): void {
    $response = $this->actingAs($this->seller(), 'seller')
        ->get('/seller/listings/create?'.http_build_query(['title' => 'Sunset Ridge', 'shape' => 'versions']));

    $response->assertOk();
    $response->assertSee('Sunset Ridge');
    $response->assertSee('What do buyers choose between?');
    $response->assertSee('each option priced on its own');
    $response->assertDontSee('for="price"', escape: false);
});

it('DSGN-003 continues to the extras landing screen', function (): void {
    $response = $this->actingAs($this->seller(), 'seller')
        ->get('/seller/listings/create?'.http_build_query(['title' => 'Maple Serving Board', 'shape' => 'extras']));

    $response->assertOk();
    $response->assertSee('Maple Serving Board');
    $response->assertSee('The first extra buyers choose');
    $response->assertSee('adds to your price');
    $response->assertSee('Create with just the price');
});

it('DSGN-003 falls back to the question screen for an unrecognized shape', function (): void {
    $response = $this->actingAs($this->seller(), 'seller')
        ->get('/seller/listings/create?'.http_build_query(['title' => 'X', 'shape' => 'bogus']));

    $response->assertOk();
    $response->assertSee('What are you selling?');
});

it('DSGN-003 keeps the legacy flat-form fields off every create screen', function (): void {
    $seller = $this->actingAs($this->seller(), 'seller');

    $seller->get('/seller/listings/create')
        ->assertDontSee('name="dimensions"', escape: false)
        ->assertDontSee('name="category_id"', escape: false)
        ->assertDontSee('name="image"', escape: false)
        ->assertDontSee('name="description"', escape: false);

    foreach (['one', 'versions', 'extras'] as $shape) {
        $seller->get('/seller/listings/create?'.http_build_query(['title' => 'X', 'shape' => $shape]))
            ->assertDontSee('name="dimensions"', escape: false)
            ->assertDontSee('name="category_id"', escape: false)
            ->assertDontSee('name="image"', escape: false)
            ->assertDontSee('name="description"', escape: false);
    }
});

it('DSGN-003 retires the flat listing form view', function (): void {
    expect(\Illuminate\Support\Facades\View::exists('seller.listings.form'))->toBeFalse();
});

it('creates a listing from the form and lands on the hub', function () use ($form): void {
    $seller = $this->seller();

    $response = $this->actingAs($seller, 'seller')->post('/seller/listings', $form());

    $listing = Listing::where('seller_id', $seller->id)->sole();
    $response->assertRedirect(route('seller.listings.edit', $listing));
    expect($listing->title)->toBe('Harbour at Dusk')
        ->and($listing->price_cents)->toBe(24900)
        ->and($listing->quantity)->toBe(1)
        ->and($listing->status)->toBe(ListingStatus::Draft);

    $hub = $this->actingAs($seller, 'seller')->get(route('seller.listings.edit', $listing));

    $hub->assertOk();
    $hub->assertSee('Your item');
    $hub->assertSee('$249.00');
    $hub->assertSee('1 in stock');
});

it('DSGN-003 creates a made-to-order one-thing listing with a null quantity that stays available', function () use ($form): void {
    $seller = $this->seller();

    $response = $this->actingAs($seller, 'seller')
        ->post('/seller/listings', $form(['quantity' => '', 'made_to_order' => '1']));

    $listing = Listing::where('seller_id', $seller->id)->sole();
    $response->assertRedirect(route('seller.listings.edit', $listing));
    expect($listing->quantity)->toBeNull();

    $hub = $this->actingAs($seller, 'seller')->get(route('seller.listings.edit', $listing));
    $hub->assertSee('Made to order');
});

it('DSGN-003 creates a versions listing: standalone axis, priced options, one combination per version, synced price', function (): void {
    $seller = $this->seller();

    $response = $this->actingAs($seller, 'seller')->post('/seller/listings', [
        'shape' => 'versions',
        'title' => 'Sunset Ridge',
        'choice_name' => 'Size',
        'versions' => [
            ['label' => '8x10', 'price' => '18.00'],
            ['label' => '11x14', 'price' => '24.00'],
            ['label' => '16x20', 'price' => '34.00'],
            ['label' => '', 'price' => ''],
        ],
    ]);

    $listing = Listing::where('seller_id', $seller->id)->sole();
    $response->assertRedirect(route('seller.listings.edit', $listing));

    $axis = OptionAxis::where('listing_id', $listing->id)->sole();
    expect($axis->name)->toBe('Size')
        ->and($axis->pricing_mode)->toBe(\App\Domain\Configurator\PricingMode::Standalone)
        ->and($axis->optionValues()->count())->toBe(3)
        ->and(Variant::where('listing_id', $listing->id)->count())->toBe(3)
        ->and(Variant::where('listing_id', $listing->id)->whereNotNull('quantity')->count())->toBe(0)
        ->and($listing->refresh()->price_cents)->toBe(1800);
});

it('DSGN-003 drops a fully blank version row and rejects an incomplete one', function (): void {
    $seller = $this->seller();

    $response = $this->actingAs($seller, 'seller')->post('/seller/listings', [
        'shape' => 'versions',
        'title' => 'Sunset Ridge',
        'choice_name' => 'Size',
        'versions' => [
            ['label' => '8x10', 'price' => '18.00'],
            ['label' => '', 'price' => ''],
            ['label' => 'Bad row', 'price' => ''],
        ],
    ]);

    $response->assertSessionHasErrors('versions.2.price');
    expect(Listing::count())->toBe(0);
});

it('DSGN-003 creates an extras listing with an add_on axis, options, and combinations', function (): void {
    $seller = $this->seller();

    $response = $this->actingAs($seller, 'seller')->post('/seller/listings', [
        'shape' => 'extras',
        'title' => 'Maple Serving Board',
        'price' => '46.00',
        'quantity' => 12,
        'extra_choice_name' => 'Finish',
        'extra_options' => [
            ['label' => 'Oil finish', 'price' => '+0.00'],
            ['label' => 'Carved handle', 'price' => '+14.00'],
        ],
    ]);

    $listing = Listing::where('seller_id', $seller->id)->sole();
    $response->assertRedirect(route('seller.listings.edit', $listing));
    expect($listing->price_cents)->toBe(4600);

    $axis = OptionAxis::where('listing_id', $listing->id)->sole();
    expect($axis->name)->toBe('Finish')
        ->and($axis->pricing_mode)->toBe(\App\Domain\Configurator\PricingMode::AddOn)
        ->and($axis->optionValues()->count())->toBe(2)
        ->and(Variant::where('listing_id', $listing->id)->count())->toBe(2)
        ->and(Variant::where('listing_id', $listing->id)->whereNotNull('quantity')->count())->toBe(0);
});

it('DSGN-003 skips the extra via the plain-price link and creates an axis-free listing', function (): void {
    $seller = $this->seller();

    $response = $this->actingAs($seller, 'seller')->post('/seller/listings', [
        'shape' => 'extras',
        'title' => 'Maple Serving Board',
        'price' => '46.00',
        'quantity' => 12,
        'extra_choice_name' => 'Finish',
        'extra_options' => [['label' => 'Oil finish', 'price' => '+0.00']],
        'skip_extra' => '1',
    ]);

    $listing = Listing::where('seller_id', $seller->id)->sole();
    $response->assertRedirect(route('seller.listings.edit', $listing));
    expect(OptionAxis::where('listing_id', $listing->id)->count())->toBe(0)
        ->and($listing->hasOwnPriceAndStock())->toBeTrue();
});

it('DSGN-003 skips the extra by leaving its fields blank, with no skip flag at all', function (): void {
    $seller = $this->seller();

    $response = $this->actingAs($seller, 'seller')->post('/seller/listings', [
        'shape' => 'extras',
        'title' => 'Maple Serving Board',
        'price' => '46.00',
        'quantity' => 12,
    ]);

    $listing = Listing::where('seller_id', $seller->id)->sole();
    $response->assertRedirect(route('seller.listings.edit', $listing));
    expect(OptionAxis::where('listing_id', $listing->id)->count())->toBe(0);
});

it('renders the activity page', function (): void {
    $seller = $this->seller();
    $listing = $this->listing($seller, ['title' => 'Harbour at Dusk']);

    $response = $this->actingAs($seller, 'seller')->get("/seller/listings/{$listing->id}");

    $response->assertOk();
    $response->assertSee('Harbour at Dusk');
});

it('reads the removal reason on its own listing page', function (): void {
    $seller = $this->seller();
    $listing = $this->listing($seller);
    ListingRemoval::factory()->create(['listing_id' => $listing->id, 'reason' => 'Under review for a copyright claim.']);

    $response = $this->actingAs($seller, 'seller')->get("/seller/listings/{$listing->id}");

    $response->assertOk();
    $response->assertSee('Under review for a copyright claim.');
    $response->assertSee('Removed from the storefront');
});

it('shows no removal notice on a listing that was never removed', function (): void {
    $seller = $this->seller();
    $listing = $this->listing($seller);

    $response = $this->actingAs($seller, 'seller')->get("/seller/listings/{$listing->id}");

    $response->assertOk();
    $response->assertDontSee('Removed from the storefront');
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
    $response->assertSee($order->id);
});

it('renders the activity page on a fixed number of queries however many events the listing recorded', function (): void {
    $seller = $this->seller();
    $listing = $this->listing($seller);
    $recordListingEvent = app(RecordListingEvent::class);
    foreach (range(1, 20) as $hour) {
        $recordListingEvent($listing, null, ListingEventType::View, new DateTimeImmutable(now()->toDateTimeString()));
    }

    $response = $this->actingAs($seller, 'seller')
        // +1 for the page-view roll-up's upsert, which runs after every
        // countable response (RollUpPageViews); +1 for the active-removal
        // eager load.
        ->expectsDatabaseQueryCount(7)
        ->get("/seller/listings/{$listing->id}");

    $response->assertOk();
});

it('renders the basics screen with the price in dollars', function (): void {
    $seller = $this->seller();
    $listing = $this->listing($seller, ['title' => 'Harbour at Dusk', 'price_cents' => 24900]);

    $response = $this->actingAs($seller, 'seller')->get("/seller/listings/{$listing->id}/basics");

    $response->assertOk();
    $response->assertSee('value="249.00"', escape: false);
});

it('preselects the listings current category on the basics screen', function (): void {
    $seller = $this->seller();
    $category = Category::factory()->create(['name' => 'Jewelry']);
    $listing = $this->listing($seller, ['category_id' => $category->id]);

    $response = $this->actingAs($seller, 'seller')->get("/seller/listings/{$listing->id}/basics");

    $response->assertSee('value="'.$category->id.'" selected', escape: false);
});

it('shows an item facts control for a categorized listing with attribute grants', function (): void {
    $seller = $this->seller();
    $category = Category::factory()->create();
    $property = Property::factory()->create(['name' => 'Material']);
    PropertyValue::factory()->create(['property_id' => $property->id, 'label' => 'Walnut']);
    CategoryProperty::factory()->create(['category_id' => $category->id, 'property_id' => $property->id, 'usable_as_attribute' => true]);
    $listing = $this->listing($seller, ['category_id' => $category->id]);

    $response = $this->actingAs($seller, 'seller')->get("/seller/listings/{$listing->id}/basics");

    $response->assertSee('Material');
    $response->assertSee('Walnut');
});

it('D5: the category gates which facts the basics screen asks, only the grant for that category appears', function (): void {
    $seller = $this->seller();
    $jewelry = Category::factory()->create(['name' => 'Jewelry']);
    $metalProperty = Property::factory()->create(['name' => 'Metal']);
    CategoryProperty::factory()->create(['category_id' => $jewelry->id, 'property_id' => $metalProperty->id, 'usable_as_attribute' => true]);
    $pottery = Category::factory()->create(['name' => 'Pottery']);
    $glazeProperty = Property::factory()->create(['name' => 'Glaze']);
    CategoryProperty::factory()->create(['category_id' => $pottery->id, 'property_id' => $glazeProperty->id, 'usable_as_attribute' => true]);
    $listing = $this->listing($seller, ['category_id' => $jewelry->id]);

    $response = $this->actingAs($seller, 'seller')->get("/seller/listings/{$listing->id}/basics");

    $response->assertSee('Metal');
    $response->assertDontSee('Glaze');
});

it('shows no item facts control for an uncategorized listing', function (): void {
    $seller = $this->seller();
    $listing = $this->listing($seller);

    $response = $this->actingAs($seller, 'seller')->get("/seller/listings/{$listing->id}/basics");

    $response->assertDontSee('Save facts');
});

it('links a missing required attribute to its control on the basics screen, from the hub', function (): void {
    $seller = $this->seller();
    $category = Category::factory()->create();
    $property = Property::factory()->create();
    CategoryProperty::factory()->create([
        'category_id' => $category->id,
        'property_id' => $property->id,
        'usable_as_attribute' => true,
        'required' => true,
    ]);
    $listing = $this->listing($seller, ['category_id' => $category->id, 'status' => ListingStatus::Draft]);

    $response = $this->actingAs($seller, 'seller')->get("/seller/listings/{$listing->id}/edit");

    $response->assertSee(route('seller.listings.basics.edit', $listing).'#attribute-'.$property->id, escape: false);
});

it('preselects a listings existing attribute values on the basics screen', function (): void {
    $seller = $this->seller();
    $category = Category::factory()->create();
    $property = Property::factory()->create();
    $value = PropertyValue::factory()->create(['property_id' => $property->id, 'label' => 'Brass']);
    CategoryProperty::factory()->create(['category_id' => $category->id, 'property_id' => $property->id, 'usable_as_attribute' => true]);
    $listing = $this->listing($seller, ['category_id' => $category->id]);
    ListingAttribute::factory()->create(['listing_id' => $listing->id, 'property_id' => $property->id, 'property_value_id' => $value->id]);

    $response = $this->actingAs($seller, 'seller')->get("/seller/listings/{$listing->id}/basics");

    $response->assertSee('value="'.$value->id.'" selected', escape: false);
});

it('renders the basics screen as a PUT form', function (): void {
    $seller = $this->seller();
    $listing = $this->listing($seller);

    $response = $this->actingAs($seller, 'seller')->get("/seller/listings/{$listing->id}/basics");

    $response->assertSee('<input type="hidden" name="_method" value="PUT">', escape: false);
});

it('shows price and how many you have on the basics screen for an unconfigured listing', function (): void {
    $seller = $this->seller();
    $listing = $this->listing($seller);

    $response = $this->actingAs($seller, 'seller')->get("/seller/listings/{$listing->id}/basics");

    $response->assertSee('for="price"', escape: false);
    $response->assertSee('How many you have');
});

it('omits price and how many you have from the basics screen once the listing offers a choice', function (): void {
    $seller = $this->seller();
    $listing = $this->listing($seller);
    OptionAxis::factory()->create(['listing_id' => $listing->id]);

    $response = $this->actingAs($seller, 'seller')->get("/seller/listings/{$listing->id}/basics");

    $response->assertDontSee('for="price"', escape: false);
    $response->assertDontSee('How many you have');
});

it('DSGN-003 checks the made-to-order box on the basics screen for a null quantity', function (): void {
    $seller = $this->seller();
    $listing = $this->listing($seller, ['quantity' => null]);

    $response = $this->actingAs($seller, 'seller')->get("/seller/listings/{$listing->id}/basics");

    $response->assertSee('value="1" checked', escape: false);
});

it('DSGN-003 leaves the made-to-order box unchecked on the basics screen for a tracked quantity', function (): void {
    $seller = $this->seller();
    $listing = $this->listing($seller, ['quantity' => 5]);

    $response = $this->actingAs($seller, 'seller')->get("/seller/listings/{$listing->id}/basics");

    $response->assertDontSee('value="1" checked', escape: false);
});

it('DSGN-003 saves made to order from the basics screen, nulling the quantity', function (): void {
    $seller = $this->seller();
    $listing = $this->listing($seller, ['quantity' => 5]);

    $response = $this->actingAs($seller, 'seller')->put("/seller/listings/{$listing->id}", [
        'title' => $listing->title, 'price' => '10.00', 'made_to_order' => '1',
    ]);

    $response->assertRedirect(route('seller.listings.basics.edit', $listing));
    expect($listing->refresh()->quantity)->toBeNull();
});

it('DSGN-003 unchecking made to order on the basics screen restores a required, tracked quantity', function (): void {
    $seller = $this->seller();
    $listing = $this->listing($seller, ['quantity' => null]);

    $response = $this->actingAs($seller, 'seller')->put("/seller/listings/{$listing->id}", [
        'title' => $listing->title, 'price' => '10.00', 'quantity' => 7,
    ]);

    $response->assertRedirect(route('seller.listings.basics.edit', $listing));
    expect($listing->refresh()->quantity)->toBe(7);
});

it('saves the title and item facts from the basics screen', function (): void {
    $seller = $this->seller();
    $category = Category::factory()->create();
    $property = Property::factory()->create(['name' => 'Material']);
    $value = PropertyValue::factory()->create(['property_id' => $property->id, 'label' => 'Walnut']);
    CategoryProperty::factory()->create(['category_id' => $category->id, 'property_id' => $property->id, 'usable_as_attribute' => true]);
    $listing = $this->listing($seller, ['category_id' => $category->id, 'title' => 'Old title']);

    $response = $this->actingAs($seller, 'seller')->put("/seller/listings/{$listing->id}", [
        'title' => 'New title', 'price' => '10.00', 'quantity' => 1, 'category_id' => $category->id,
    ]);
    $response->assertRedirect(route('seller.listings.basics.edit', $listing));
    expect($listing->refresh()->title)->toBe('New title');

    $attributes = $this->actingAs($seller, 'seller')->put("/seller/listings/{$listing->id}/attributes", [
        'attribute' => [$property->id => [$value->id]],
    ]);
    $attributes->assertRedirect(route('seller.listings.basics.edit', $listing));
    expect(ListingAttribute::where('listing_id', $listing->id)->sole()->property_value_id)->toBe($value->id);
});

it('a basics save does not clobber the synced price of a listing with a standalone choice', function (): void {
    $seller = $this->seller();
    $listing = $this->listing($seller, ['title' => 'Old title', 'price_cents' => 999]);
    $axis = OptionAxis::factory()->standalone()->create(['listing_id' => $listing->id]);
    OptionValue::factory()->priced(1800)->create(['axis_id' => $axis->id, 'is_default' => true, 'position' => 0]);
    \App\Support\Configurator\ListingPriceSync::sync($listing->refresh());
    expect($listing->refresh()->price_cents)->toBe(1800);

    $response = $this->actingAs($seller, 'seller')->put("/seller/listings/{$listing->id}", ['title' => 'New title']);

    $response->assertRedirect(route('seller.listings.basics.edit', $listing));
    expect($listing->refresh()->title)->toBe('New title')
        ->and($listing->refresh()->price_cents)->toBe(1800);
});

it('updates a listing from the form', function () use ($form): void {
    $seller = $this->seller();
    $listing = $this->listing($seller, ['title' => 'Old title']);

    $response = $this->actingAs($seller, 'seller')->put("/seller/listings/{$listing->id}", $form());

    $response->assertRedirect(route('seller.listings.basics.edit', $listing));
    $listing->refresh();
    expect($listing->title)->toBe('Harbour at Dusk')
        ->and($listing->price_cents)->toBe(24900);
});

it('E1: editing a live listings price after an order leaves the placed order at its old price', function () use ($form): void {
    $seller = $this->seller();
    $listing = $this->listing($seller, ['title' => 'Old title', 'price_cents' => 5000]);
    $order = $this->orderFor($this->verifiedCustomer(), $listing);

    $response = $this->actingAs($seller, 'seller')->put("/seller/listings/{$listing->id}", $form(['price' => '80.00']));

    $response->assertRedirect(route('seller.listings.basics.edit', $listing));
    expect($listing->fresh()?->price_cents)->toBe(8000)
        ->and($order->items->sole()->unit_price_cents)->toBe(5000);
});

it('rejects an update without a title', function () use ($form): void {
    $seller = $this->seller();
    $listing = $this->listing($seller, ['title' => 'Old title']);

    $response = $this->actingAs($seller, 'seller')
        ->put("/seller/listings/{$listing->id}", $form(['title' => '']));

    $response->assertSessionHasErrors('title');
    expect($listing->refresh()->title)->toBe('Old title');
});

it('DSGN-002 ignores an image on a Basics-screen save — that upload belongs to the Images screen', function () use ($form): void {
    $seller = $this->seller();
    $listing = $this->listing($seller);
    $this->listingImage($listing, ['path' => 'listings/old.jpg']);

    $this->actingAs($seller, 'seller')->put("/seller/listings/{$listing->id}", $form([
        'image' => UploadedFile::fake()->image('harbour.jpg'),
    ]));

    expect($listing->images()->sole()->path)->toBe('listings/old.jpg');
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

it('trips the listing-write limit on create, re-rendering the landing screen with nothing saved', function () use ($form): void {
    Config::set('rate_limits.listing_write', RateLimitValue::parse('1/1h', 'RATE_LIMIT_LISTING_WRITE'));
    $seller = $this->seller();
    $this->actingAs($seller, 'seller')->post('/seller/listings', $form());

    $response = $this->actingAs($seller, 'seller')->post('/seller/listings', $form(['title' => 'Second piece']));

    $response->assertStatus(429);
    $response->assertHeader('Retry-After');
    $response->assertSee('Too many requests', escape: false);
    $response->assertSee('Second piece');
    expect(Listing::where('seller_id', $seller->id)->count())->toBe(1);
});

it('trips the listing-write limit on update, re-rendering the edit form with nothing changed', function () use ($form): void {
    Config::set('rate_limits.listing_write', RateLimitValue::parse('1/1h', 'RATE_LIMIT_LISTING_WRITE'));
    $seller = $this->seller();
    $listing = $this->listing($seller, ['title' => 'Old title']);
    $this->actingAs($seller, 'seller')->post('/seller/listings', $form());

    $response = $this->actingAs($seller, 'seller')->put("/seller/listings/{$listing->id}", $form());

    $response->assertStatus(429);
    $response->assertHeader('Retry-After');
    $response->assertSee('Too many requests', escape: false);
    expect($listing->refresh()->title)->toBe('Old title');
});

it('resets the listing-write limit once its window passes', function () use ($form): void {
    Config::set('rate_limits.listing_write', RateLimitValue::parse('1/1h', 'RATE_LIMIT_LISTING_WRITE'));
    $seller = $this->seller();
    $this->actingAs($seller, 'seller')->post('/seller/listings', $form());

    $this->travel(61)->minutes();
    $response = $this->actingAs($seller, 'seller')->post('/seller/listings', $form(['title' => 'Second piece']));

    $newListing = Listing::where('seller_id', $seller->id)->where('title', 'Second piece')->sole();
    $response->assertRedirect(route('seller.listings.edit', $newListing));
    expect(Listing::where('seller_id', $seller->id)->count())->toBe(2);
});

it('DSGN-001 progressive disclosure: shows five invitations and no machinery for an unconfigured listing', function (): void {
    $seller = $this->seller();
    $listing = $this->listing($seller);

    $response = $this->actingAs($seller, 'seller')->get("/seller/listings/{$listing->id}/edit");

    $response->assertOk();
    $response->assertSee('Comes in more than one version?');
    $response->assertSee('Offer choices');
    $response->assertSee('Need an answer from the buyer?');
    $response->assertSee('Ask a question');
    $response->assertSee('Every piece one of a kind?');
    $response->assertSee('List pieces');
    $response->assertSee('Cheaper in bulk?');
    $response->assertSee('Add a discount');
    $response->assertSee('More to say?');
    $response->assertSee('Lay out the page');

    $response->assertDontSee('Choices you offer');
    $response->assertDontSee('Questions you ask the buyer');
    $response->assertDontSee('Quantity discounts');
    $response->assertDontSee('Listing page sections');
    $response->assertDontSee('Individual pieces');
    $response->assertDontSee('Configurator');
    $response->assertDontSee('Axes &amp; options', escape: false);
});

it('DSGN-001 progressive disclosure: shows summary cards with craft copy for a configured listing', function (): void {
    $seller = $this->seller();
    $listing = $this->listing($seller);
    $axis = OptionAxis::factory()->create(['listing_id' => $listing->id, 'name' => 'Size']);
    $small = OptionValue::factory()->create(['axis_id' => $axis->id, 'label' => '11 oz', 'is_default' => true, 'position' => 0]);
    $large = OptionValue::factory()->create(['axis_id' => $axis->id, 'label' => '15 oz', 'surcharge_cents' => 400, 'position' => 1]);
    $small11 = Variant::factory()->create(['listing_id' => $listing->id, 'combo_key' => 'a', 'enabled' => true]);
    $small11->options()->create(['axis_id' => $axis->id, 'option_value_id' => $small->id]);
    $large15 = Variant::factory()->create(['listing_id' => $listing->id, 'combo_key' => 'b', 'enabled' => true]);
    $large15->options()->create(['axis_id' => $axis->id, 'option_value_id' => $large->id]);

    Modifier::factory()->required()->create([
        'listing_id' => $listing->id,
        'prompt' => 'What name should we letter?',
        'add_on_price_cents' => 200,
    ]);

    QuantityBreak::factory()->create(['listing_id' => $listing->id, 'min_qty' => 6, 'discount_bps' => 1000]);
    QuantityBreak::factory()->create(['listing_id' => $listing->id, 'min_qty' => 12, 'discount_bps' => 1500]);

    DescriptionSection::factory()->create(['listing_id' => $listing->id, 'position' => 0, 'title' => 'How to order']);
    DescriptionSection::factory()->create(['listing_id' => $listing->id, 'position' => 1, 'title' => 'Care']);

    $response = $this->actingAs($seller, 'seller')->get("/seller/listings/{$listing->id}/edit");

    $response->assertOk();
    $response->assertSee('Choices you offer');
    $response->assertSee('11 oz');
    $response->assertSee('15 oz');
    $response->assertSee('+$4.00');
    $response->assertSee('2 of 2 combinations offered', escape: false);
    $response->assertSee('Combinations &amp; stock', escape: false);

    $response->assertSee('Questions you ask the buyer');
    $response->assertSee('&ldquo;What name should we letter?&rdquo;', escape: false);
    $response->assertSee('+$2.00');
    $response->assertSee('must answer');

    $response->assertSee('Quantity discounts');
    $response->assertSee('6 or more — 10% off each · 12 or more — 15% off each');

    $response->assertSee('Listing page sections');
    $response->assertSee('How to order · Care');

    $response->assertDontSee('Offer choices');
    $response->assertDontSee('Ask a question');
});

it('shows the price and stock line on the hub for an unconfigured listing', function (): void {
    $seller = $this->seller();
    $listing = $this->listing($seller, ['price_cents' => 2800, 'quantity' => 4]);

    $response = $this->actingAs($seller, 'seller')->get("/seller/listings/{$listing->id}/edit");

    $response->assertSee('$28.00');
    $response->assertSee('4 in stock');
});

it('hides the price and stock line on the hub once the listing offers a choice', function (): void {
    $seller = $this->seller();
    $listing = $this->listing($seller, ['price_cents' => 2800, 'quantity' => 4]);
    OptionAxis::factory()->create(['listing_id' => $listing->id]);

    $response = $this->actingAs($seller, 'seller')->get("/seller/listings/{$listing->id}/edit");

    $response->assertDontSee('in stock');
});

it('hides the price and stock line on the hub once the listing carries a serialized piece', function (): void {
    $seller = $this->seller();
    $listing = $this->listing($seller, ['price_cents' => 2800, 'quantity' => 4]);
    Variant::factory()->serialized()->create(['listing_id' => $listing->id, 'combo_key' => 'a']);

    $response = $this->actingAs($seller, 'seller')->get("/seller/listings/{$listing->id}/edit");

    $response->assertDontSee('in stock');
});

it('no longer renders the flat listing form on the hub', function (): void {
    $seller = $this->seller();
    $listing = $this->listing($seller);

    $response = $this->actingAs($seller, 'seller')->get("/seller/listings/{$listing->id}/edit");

    $response->assertDontSee('name="title"', escape: false);
    $response->assertDontSee('name="price"', escape: false);
});

it('DSGN-002 repeats each choice’s pricing-mode pill on the hub’s Choices row', function (): void {
    $seller = $this->seller();
    $listing = $this->listing($seller, ['price_cents' => 1800]);
    $size = OptionAxis::factory()->standalone()->create(['listing_id' => $listing->id, 'name' => 'Size']);
    OptionValue::factory()->priced(1800)->create(['axis_id' => $size->id, 'label' => '8x10', 'is_default' => true]);
    $frame = OptionAxis::factory()->addOn()->create(['listing_id' => $listing->id, 'name' => 'Frame']);
    OptionValue::factory()->create(['axis_id' => $frame->id, 'label' => 'Unframed', 'surcharge_cents' => 0, 'is_default' => true]);

    $response = $this->actingAs($seller, 'seller')->get("/seller/listings/{$listing->id}/edit");

    $response->assertSee('each option priced on its own');
    $response->assertSee('adds to your price');
});

it('BUG-013: shows the buyer-view panel as a title, price, and add-to-cart preview for an unconfigured listing on the hub', function (): void {
    $seller = $this->seller();
    $listing = $this->listing($seller, ['title' => 'Winter Elm', 'price_cents' => 4100]);

    $response = $this->actingAs($seller, 'seller')->get("/seller/listings/{$listing->id}/edit");

    $response->assertSee('Winter Elm');
    $response->assertSee('$41.00');
    $response->assertSee('Add to cart');
    $response->assertDontSee('Nothing here yet for a buyer to configure');
});

it('shows the buyer-view panel resolved to a choice for a configured listing on the hub', function (): void {
    $seller = $this->seller();
    $listing = $this->listing($seller);
    $axis = OptionAxis::factory()->create(['listing_id' => $listing->id, 'name' => 'Size']);
    OptionValue::factory()->create(['axis_id' => $axis->id, 'label' => '8x10', 'is_default' => true]);

    $response = $this->actingAs($seller, 'seller')->get("/seller/listings/{$listing->id}/edit");

    $response->assertSee('What buyers see', escape: false);
    $response->assertSee('Size');
    $response->assertSee('8x10');
});

it('shows an invitation-style line on the Images row for a listing with no images', function (): void {
    $seller = $this->seller();
    $listing = $this->listing($seller);

    $response = $this->actingAs($seller, 'seller')->get("/seller/listings/{$listing->id}/edit");

    $response->assertSee('Images');
    $response->assertSee('No images yet — buyers see a placeholder.');
});

it('shows up to three thumbnails and a more-count tile on the Images row', function (): void {
    $seller = $this->seller();
    $listing = $this->listing($seller);
    foreach (range(0, 4) as $position) {
        $this->listingImage($listing, ['position' => $position]);
    }

    $response = $this->actingAs($seller, 'seller')->get("/seller/listings/{$listing->id}/edit");

    $response->assertSee('+2');
});

it('shows an available-and-sold piece summary for a listing with a serialized combination', function (): void {
    $seller = $this->seller();
    $listing = $this->listing($seller);
    $variant = Variant::factory()->serialized()->create(['listing_id' => $listing->id, 'combo_key' => 'a']);
    \App\Models\Unit::factory()->create(['variant_id' => $variant->id, 'state' => \App\Domain\Configurator\UnitState::Available]);
    \App\Models\Unit::factory()->create(['variant_id' => $variant->id, 'state' => \App\Domain\Configurator\UnitState::Sold]);

    $response = $this->actingAs($seller, 'seller')->get("/seller/listings/{$listing->id}/edit");

    $response->assertSee('Individual pieces');
    $response->assertSee('2 pieces');
    $response->assertSee('1 available');
    $response->assertSee('1 sold');
    $response->assertDontSee('Every piece one of a kind?');
});

it('shows the Medium custom-value hint on an item fact grant named Medium', function (): void {
    $seller = $this->seller();
    $category = Category::factory()->create();
    $property = Property::factory()->create(['name' => 'Medium']);
    PropertyValue::factory()->create(['property_id' => $property->id, 'label' => 'Ceramic']);
    CategoryProperty::factory()->create(['category_id' => $category->id, 'property_id' => $property->id, 'usable_as_attribute' => true]);
    $listing = $this->listing($seller, ['category_id' => $category->id]);

    $response = $this->actingAs($seller, 'seller')->get("/seller/listings/{$listing->id}/basics");

    $response->assertSee("Not on the list? Custom values aren't available yet — say it in the description.", escape: false);
});

it('does not show the physical-goods footer line', function (): void {
    $seller = $this->seller();
    $listing = $this->listing($seller);

    $response = $this->actingAs($seller, 'seller')->get("/seller/listings/{$listing->id}/edit");

    $response->assertDontSee("Art Store sells physical goods — digital downloads and file delivery aren't supported yet.", escape: false);
});

it('shows the ready panel and publishes a draft with no issues from its button', function (): void {
    $seller = $this->seller();
    $listing = $this->listing($seller, ['status' => ListingStatus::Draft]);

    $response = $this->actingAs($seller, 'seller')->get("/seller/listings/{$listing->id}/edit");

    $response->assertSee('Ready to go live — nothing is missing.');
    $response->assertSee(route('seller.listings.status', $listing), escape: false);
    $response->assertSee('Put it up for sale');
    $response->assertDontSee('Before this can go live');

    $publish = $this->actingAs($seller, 'seller')->post(route('seller.listings.status', $listing), ['status' => 'for_sale']);

    $publish->assertRedirect(route('seller.listings.index'));
    expect($listing->refresh()->status)->toBe(ListingStatus::ForSale);
});

it('shows the live-editing hint instead of a publish panel on a listing already for sale', function (): void {
    $seller = $this->seller();
    $listing = $this->listing($seller, ['status' => ListingStatus::ForSale]);

    $response = $this->actingAs($seller, 'seller')->get("/seller/listings/{$listing->id}/edit");

    $response->assertSee("Editing a live listing never changes an order that's already placed — every order keeps the exact price and choices its buyer agreed to.", escape: false);
    $response->assertDontSee('Ready to go live — nothing is missing.');
    $response->assertDontSee('Before this can go live');
    $response->assertDontSee('Put it up for sale');
});

it('reaches the negative-priced-combination publish issue end to end', function (): void {
    $seller = $this->seller();
    $listing = $this->listing($seller, ['status' => ListingStatus::Draft]);
    $axis = OptionAxis::factory()->create(['listing_id' => $listing->id]);
    $value = OptionValue::factory()->create(['axis_id' => $axis->id]);
    $variant = Variant::factory()->create(['listing_id' => $listing->id, 'combo_key' => 'a', 'price_override_cents' => -500]);
    $variant->options()->create(['axis_id' => $axis->id, 'option_value_id' => $value->id]);

    $response = $this->actingAs($seller, 'seller')->get("/seller/listings/{$listing->id}/edit");

    $response->assertSee("buyers can't be charged a negative amount.");
    $response->assertSee(route('seller.listings.variants.index', $listing).'#'.$variant->id, escape: false);
});

it('reaches the combination-missing-a-choice publish issue end to end', function (): void {
    $seller = $this->seller();
    $listing = $this->listing($seller, ['status' => ListingStatus::Draft]);
    OptionAxis::factory()->create(['listing_id' => $listing->id]);
    $variant = Variant::factory()->create(['listing_id' => $listing->id, 'combo_key' => 'a']);

    $response = $this->actingAs($seller, 'seller')->get("/seller/listings/{$listing->id}/edit");

    $response->assertSee('pick one for each before it can be offered', escape: false);
    $response->assertSee(route('seller.listings.variants.index', $listing).'#'.$variant->id, escape: false);
});

it('reaches the empty-serialized-piece-list publish issue end to end', function (): void {
    $seller = $this->seller();
    $listing = $this->listing($seller, ['status' => ListingStatus::Draft]);
    $variant = Variant::factory()->serialized()->create(['listing_id' => $listing->id, 'combo_key' => 'a']);

    $response = $this->actingAs($seller, 'seller')->get("/seller/listings/{$listing->id}/edit");

    $response->assertSee("the piece list is empty — there's nothing to sell yet.");
    $response->assertSee(route('seller.listings.variants.units.index', [$listing, $variant]), escape: false);
});

it('reaches the missing-required-item-fact publish issue end to end', function (): void {
    $seller = $this->seller();
    $category = Category::factory()->create();
    $property = Property::factory()->create();
    CategoryProperty::factory()->create([
        'category_id' => $category->id,
        'property_id' => $property->id,
        'usable_as_attribute' => true,
        'required' => true,
    ]);
    $listing = $this->listing($seller, ['category_id' => $category->id, 'status' => ListingStatus::Draft]);

    $response = $this->actingAs($seller, 'seller')->get("/seller/listings/{$listing->id}/edit");

    $response->assertSee("Say what it's made of — buyers filter by it.");
    $response->assertSee(route('seller.listings.basics.edit', $listing).'#attribute-'.$property->id, escape: false);
});

it('reaches the too-many-options publish issue end to end', function (): void {
    $seller = $this->seller();
    $listing = $this->listing($seller, ['status' => ListingStatus::Draft]);
    $axis = OptionAxis::factory()->create(['listing_id' => $listing->id]);
    OptionValue::factory()->count(71)->sequence(fn (Sequence $sequence) => ['position' => $sequence->index])->create(['axis_id' => $axis->id]);

    $response = $this->actingAs($seller, 'seller')->get("/seller/listings/{$listing->id}/edit");

    $response->assertSee('trim its list before this can go live', escape: false);
    $response->assertSee(route('seller.listings.option-axes.index', $listing), escape: false);
});

it('reaches the too-many-combinations publish issue end to end', function (): void {
    $seller = $this->seller();
    $listing = $this->listing($seller, ['status' => ListingStatus::Draft]);
    Variant::factory()->count(501)->sequence(fn (Sequence $sequence) => ['combo_key' => (string) $sequence->index])->create(['listing_id' => $listing->id]);

    $response = $this->actingAs($seller, 'seller')->get("/seller/listings/{$listing->id}/edit");

    $response->assertSee('holds more combinations than the platform allows', escape: false);
    $response->assertSee(route('seller.listings.variants.index', $listing), escape: false);
});

it('reaches the too-many-questions publish issue end to end', function (): void {
    $seller = $this->seller();
    $listing = $this->listing($seller, ['status' => ListingStatus::Draft]);
    Modifier::factory()->count(6)->sequence(fn (Sequence $sequence) => ['position' => $sequence->index])->create(['listing_id' => $listing->id]);

    $response = $this->actingAs($seller, 'seller')->get("/seller/listings/{$listing->id}/edit");

    $response->assertSee('asks more questions than the platform allows', escape: false);
    $response->assertSee(route('seller.listings.modifiers.index', $listing), escape: false);
});

it('reaches the too-many-discounts publish issue end to end', function (): void {
    $seller = $this->seller();
    $listing = $this->listing($seller, ['status' => ListingStatus::Draft]);
    QuantityBreak::factory()->count(11)->sequence(fn (Sequence $sequence) => ['min_qty' => 2 + $sequence->index])->create(['listing_id' => $listing->id]);

    $response = $this->actingAs($seller, 'seller')->get("/seller/listings/{$listing->id}/edit");

    $response->assertSee('holds more quantity discounts than the platform allows', escape: false);
    $response->assertSee(route('seller.listings.quantity-breaks.index', $listing), escape: false);
});

it('reaches the too-many-sections publish issue end to end', function (): void {
    $seller = $this->seller();
    $listing = $this->listing($seller, ['status' => ListingStatus::Draft]);
    DescriptionSection::factory()->count(16)->sequence(fn (Sequence $sequence) => ['position' => $sequence->index])->create(['listing_id' => $listing->id]);

    $response = $this->actingAs($seller, 'seller')->get("/seller/listings/{$listing->id}/edit");

    $response->assertSee('holds more page sections than the platform allows', escape: false);
    $response->assertSee(route('seller.listings.description-sections.index', $listing), escape: false);
});

it('E2: shows every publish issue at once, each naming its fix and linking to the owning field', function (): void {
    $seller = $this->seller();
    $category = Category::factory()->create();
    $property = Property::factory()->create();
    CategoryProperty::factory()->create(['category_id' => $category->id, 'property_id' => $property->id, 'usable_as_attribute' => true, 'required' => true]);
    $listing = $this->listing($seller, ['category_id' => $category->id, 'status' => ListingStatus::Draft]);
    $axis = OptionAxis::factory()->create(['listing_id' => $listing->id]);
    $value = OptionValue::factory()->create(['axis_id' => $axis->id]);
    $variant = Variant::factory()->create(['listing_id' => $listing->id, 'combo_key' => 'a', 'price_override_cents' => -500]);
    $variant->options()->create(['axis_id' => $axis->id, 'option_value_id' => $value->id]);

    $response = $this->actingAs($seller, 'seller')->get("/seller/listings/{$listing->id}/edit");

    $response->assertSeeInOrder([
        "buyers can't be charged a negative amount.",
        route('seller.listings.variants.index', $listing).'#'.$variant->id,
        "Say what it's made of — buyers filter by it.",
        route('seller.listings.basics.edit', $listing).'#attribute-'.$property->id,
    ], escape: false);
});
