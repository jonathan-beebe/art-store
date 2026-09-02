<?php

declare(strict_types=1);

namespace App\Models;

use App\Actions\Listings\RecordListingEvent;
use App\Domain\Configurator\ConfiguratorPublishValidation;
use App\Domain\Configurator\PublishIssue;
use App\Domain\Listings\ListingEventType;
use App\Domain\Listings\ListingStatus;
use App\Domain\Listings\RemovedFilter;
use DomainException;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Database\Query\Grammars\MySqlGrammar;
use Illuminate\Support\Facades\DB;

it('has no publish issues with no configurator data', function (): void {
    $listing = $this->listing($this->seller());

    expect($listing->publishIssues())->toBe([]);
});

it('folds its axes, variants, modifiers, quantity breaks, and sections into publish issues', function (): void {
    $listing = $this->listing($this->seller());
    $axis = OptionAxis::factory()->create(['listing_id' => $listing->id]);
    OptionValue::factory()->create(['axis_id' => $axis->id]);
    $variant = Variant::factory()->create(['listing_id' => $listing->id]);

    $issues = $listing->publishIssues();

    expect(array_map(fn (PublishIssue $issue): string => $issue->code, $issues))
        ->toBe(['variant_missing_axis_value'])
        ->and($issues[0]->subjectId)->toBe($variant->id);
});

it('flags too many modifiers among its publish issues', function (): void {
    $listing = $this->listing($this->seller());
    Modifier::factory()->count(ConfiguratorPublishValidation::MAX_MODIFIERS + 1)->create(['listing_id' => $listing->id]);

    expect(array_map(fn (PublishIssue $issue): string => $issue->code, $listing->publishIssues()))
        ->toBe(['too_many_modifiers']);
});

it('flags a required attribute with no value among its publish issues', function (): void {
    $category = Category::factory()->create();
    $property = Property::factory()->create();
    CategoryProperty::factory()->create([
        'category_id' => $category->id,
        'property_id' => $property->id,
        'usable_as_attribute' => true,
        'required' => true,
    ]);
    $listing = $this->listing($this->seller(), ['category_id' => $category->id]);

    $issues = $listing->publishIssues();

    expect(array_map(fn (PublishIssue $issue): string => $issue->code, $issues))->toBe(['missing_required_attribute'])
        ->and($issues[0]->subjectId)->toBe($property->id);
});

it('does not flag a required attribute the listing already holds a value for', function (): void {
    $category = Category::factory()->create();
    $property = Property::factory()->create();
    CategoryProperty::factory()->create([
        'category_id' => $category->id,
        'property_id' => $property->id,
        'usable_as_attribute' => true,
        'required' => true,
    ]);
    $value = PropertyValue::factory()->create(['property_id' => $property->id]);
    $listing = $this->listing($this->seller(), ['category_id' => $category->id]);
    ListingAttribute::factory()->create([
        'listing_id' => $listing->id,
        'property_id' => $property->id,
        'property_value_id' => $value->id,
    ]);

    expect($listing->publishIssues())->toBe([]);
});

it('ignores a required grant belonging to another category', function (): void {
    $category = Category::factory()->create();
    $otherCategory = Category::factory()->create();
    $property = Property::factory()->create();
    CategoryProperty::factory()->create([
        'category_id' => $otherCategory->id,
        'property_id' => $property->id,
        'usable_as_attribute' => true,
        'required' => true,
    ]);
    $listing = $this->listing($this->seller(), ['category_id' => $category->id]);

    expect($listing->publishIssues())->toBe([]);
});

it('surfaces only listings for sale on the storefront', function (): void {
    $seller = $this->seller();
    $forSale = $this->listing($seller);
    $this->listing($seller, ['status' => ListingStatus::Draft]);
    $this->listing($seller, ['status' => ListingStatus::Sold, 'quantity' => 0]);

    expect(Listing::query()->forSale()->pluck('id')->all())->toBe([$forSale->id]);
});

it('drops a removed listing from forSale even while its status still says for_sale', function (): void {
    $seller = $this->seller();
    $forSale = $this->listing($seller);
    $removed = $this->listing($seller);
    ListingRemoval::factory()->create(['listing_id' => $removed->id]);

    expect(Listing::query()->forSale()->pluck('id')->all())->toBe([$forSale->id]);
});

it('reads whether it carries an active removal, and its reason', function (): void {
    $listing = $this->listing($this->seller());

    expect($listing->hasActiveRemoval())->toBeFalse()
        ->and($listing->currentRemoval())->toBeNull()
        ->and($listing->removalReason())->toBeNull();

    ListingRemoval::factory()->create(['listing_id' => $listing->id, 'reason' => 'Under review.']);

    expect($listing->hasActiveRemoval())->toBeTrue()
        ->and($listing->currentRemoval()?->reason)->toBe('Under review.')
        ->and($listing->removalReason())->toBe('Under review.');
});

it('reads only the active removal when it has been removed more than once', function (): void {
    $listing = $this->listing($this->seller());
    ListingRemoval::factory()->lifted()->create(['listing_id' => $listing->id, 'reason' => 'First removal.']);
    ListingRemoval::factory()->create(['listing_id' => $listing->id, 'reason' => 'Second removal.']);

    expect($listing->removalReason())->toBe('Second removal.');
});

it('is on the storefront by status alone, and off it once removed', function (): void {
    $listing = $this->listing($this->seller());

    expect($listing->isOnStorefront())->toBeTrue();

    ListingRemoval::factory()->create(['listing_id' => $listing->id]);

    expect($listing->isOnStorefront())->toBeFalse();
});

it('drops for_sale from the transitions it offers while removed', function (): void {
    $listing = $this->listing($this->seller(), ['status' => ListingStatus::Sold]);

    expect($listing->availableTransitions())->toBe([ListingStatus::ForSale]);

    ListingRemoval::factory()->create(['listing_id' => $listing->id]);

    expect($listing->availableTransitions())->toBe([]);
});

it('reads the eager-loaded activeRemoval relation rather than a fresh query', function (): void {
    $listing = $this->listing($this->seller(), ['status' => ListingStatus::Sold]);
    ListingRemoval::factory()->create(['listing_id' => $listing->id]);
    $listing->load('activeRemoval');

    $removalQueries = 0;
    DB::listen(function (QueryExecuted $query) use (&$removalQueries): void {
        $removalQueries += str_contains($query->sql, 'listing_removals') ? 1 : 0;
    });

    expect($listing->availableTransitionsFromEagerLoad())->toBe([])
        ->and($removalQueries)->toBe(0);
});

it('falls back to a fresh check when the relation was not eager-loaded', function (): void {
    $listing = $this->listing($this->seller(), ['status' => ListingStatus::Sold]);

    expect($listing->availableTransitionsFromEagerLoad())->toBe([ListingStatus::ForSale]);

    ListingRemoval::factory()->create(['listing_id' => $listing->id]);

    expect($listing->availableTransitionsFromEagerLoad())->toBe([]);
});

it('narrows the admin list by removal state', function (RemovedFilter $filter, string $expectedTitle): void {
    $seller = $this->seller();
    $this->listing($seller, ['title' => 'Visible piece']);
    $removedListing = $this->listing($seller, ['title' => 'Removed piece']);
    ListingRemoval::factory()->create(['listing_id' => $removedListing->id]);

    $titles = Listing::query()->ofRemoval($filter)->orderBy('title')->pluck('title')->all();

    expect($titles)->toContain($expectedTitle);
})->with([
    'visible only shows the untouched listing' => [RemovedFilter::Visible, 'Visible piece'],
    'removed only shows the removed listing' => [RemovedFilter::Removed, 'Removed piece'],
]);

it('shows every listing when the removed filter is any or absent', function (?RemovedFilter $filter): void {
    $seller = $this->seller();
    $this->listing($seller);
    $removedListing = $this->listing($seller);
    ListingRemoval::factory()->create(['listing_id' => $removedListing->id]);

    expect(Listing::query()->ofRemoval($filter)->count())->toBe(2);
})->with([
    'no filter at all' => [null],
    'the explicit any case' => [RemovedFilter::Any],
]);

it('narrows by its Medium attribute, case-insensitively, and adds no clause for null', function (): void {
    $seller = $this->seller();
    $ceramic = $this->listing($seller, ['title' => 'Kiln Study']);
    $oil = $this->listing($seller, ['title' => 'Harbour at Dawn']);
    $unattributed = $this->listing($seller, ['title' => 'Field Sketch']);
    $this->mediumAttribute($ceramic, 'Ceramic');
    $this->mediumAttribute($oil, 'Oil');

    expect(Listing::query()->ofMediumAttribute('ceramic')->pluck('title')->all())->toBe(['Kiln Study'])
        ->and(Listing::query()->ofMediumAttribute('bronze')->count())->toBe(0)
        ->and(Listing::query()->ofMediumAttribute(null)->count())->toBe(3)
        ->and(Listing::query()->ofMediumAttribute('ceramic')->pluck('title')->all())->not->toContain($unattributed->title);
});

it('narrows by a LIKE pattern over title, description, and Medium label, and adds no clause for null', function (): void {
    $seller = $this->seller();
    $titleMatch = $this->listing($seller, ['title' => 'Harbour at Dawn', 'description' => 'A quiet morning.']);
    $descriptionMatch = $this->listing($seller, ['title' => 'Kiln Study', 'description' => 'Fired in a harbour town.']);
    $mediumMatch = $this->listing($seller, ['title' => 'Field Notes', 'description' => 'Pencil on paper.']);
    $this->mediumAttribute($mediumMatch, 'Harbour Blue Print');
    $noMatch = $this->listing($seller, ['title' => 'Winter Elm', 'description' => 'Bare branches.']);

    $matched = Listing::query()->ofSearchTerm('%harbour%')->pluck('title')->all();

    expect($matched)->toContain($titleMatch->title, $descriptionMatch->title, $mediumMatch->title)
        ->and($matched)->not->toContain($noMatch->title)
        ->and(Listing::query()->ofSearchTerm(null)->count())->toBe(4);
});

it('narrows by category path prefix, including descendants, and adds no clause for null', function (): void {
    $jewelry = Category::factory()->create(['path' => '/jewelry/']);
    $rings = Category::factory()->childOf($jewelry, 'Rings')->create();
    $furniture = Category::factory()->create(['path' => '/furniture/']);
    $seller = $this->seller();
    $inJewelry = $this->listing($seller, ['title' => 'Pearl Necklace', 'category_id' => $jewelry->id]);
    $inRings = $this->listing($seller, ['title' => 'Gold Band', 'category_id' => $rings->id]);
    $inFurniture = $this->listing($seller, ['title' => 'Oak Table', 'category_id' => $furniture->id]);
    $uncategorized = $this->listing($seller, ['title' => 'Loose Sketch']);

    $matched = Listing::query()->ofCategoryPathPrefix('/jewelry/')->pluck('title')->all();

    expect($matched)->toContain($inJewelry->title, $inRings->title)
        ->and($matched)->not->toContain($inFurniture->title, $uncategorized->title)
        ->and(Listing::query()->ofCategoryPathPrefix('/jewelry/rings/')->pluck('title')->all())->toBe([$inRings->title])
        ->and(Listing::query()->ofCategoryPathPrefix(null)->count())->toBe(4);
});

it('reads its Medium attribute label, or null with none set', function (): void {
    $seller = $this->seller();
    $attributed = $this->listing($seller);
    $this->mediumAttribute($attributed, 'Ceramic');
    $unattributed = $this->listing($seller);

    expect($attributed->mediumAttributeLabel())->toBe('Ceramic')
        ->and($unattributed->mediumAttributeLabel())->toBeNull();
});

it('keeps for sale and sold on the storefront and leaves draft and archived off it', function (): void {
    $seller = $this->seller();
    $forSale = $this->listing($seller);
    $sold = $this->listing($seller, ['status' => ListingStatus::Sold, 'quantity' => 0]);
    $this->listing($seller, ['status' => ListingStatus::Draft]);
    $this->listing($seller, ['status' => ListingStatus::Archived]);

    $reachable = Listing::query()->onStorefront()->pluck('id')->all();

    expect($reachable)->toHaveCount(2)
        ->toContain($forSale->id)
        ->toContain($sold->id);
});

it('drops a removed listing from the storefront set even while its status still says for_sale', function (): void {
    $seller = $this->seller();
    $visible = $this->listing($seller);
    $removed = $this->listing($seller);
    ListingRemoval::factory()->create(['listing_id' => $removed->id]);

    expect(Listing::query()->onStorefront()->pluck('id')->all())->toBe([$visible->id]);
});

it('puts a listing back on the storefront set once its removal is lifted', function (): void {
    $listing = $this->listing($this->seller());
    ListingRemoval::factory()->lifted()->create(['listing_id' => $listing->id]);

    expect(Listing::query()->onStorefront()->pluck('id')->all())->toBe([$listing->id]);
});

it('takes the row a moderation decision is judged against for update', function (): void {
    // SQLite has no row lock and its grammar compiles the clause away, so the
    // query is compiled here with the grammar of a database that does have
    // one — what the same read asks for in production.
    $query = Listing::query()->lockedForModeration()->toBase();

    expect((new MySqlGrammar(DB::connection()))->compileSelect($query))->toEndWith('for update');
});

it('re-reads the locked row rather than trusting the instance it was handed', function (): void {
    $listing = $this->listing($this->seller(), ['title' => 'Harbour at Dawn']);
    Listing::whereKey($listing->id)->update(['title' => 'Harbour at Dusk']);

    expect($listing->takeForModeration()->title)->toBe('Harbour at Dusk');
});

it('takes the rows placement reads for update, in id order', function (): void {
    // SQLite has no row lock and its grammar compiles the clause away, so the
    // query is compiled here with the grammar of a database that does have
    // one — what the same read asks for in production.
    $query = Listing::query()->lockedForPlacement()->toBase();

    expect((new MySqlGrammar(DB::connection()))->compileSelect($query))
        ->toContain('order by `id` asc')
        ->toEndWith('for update');
});

it('reads whether it can still be bought', function (): void {
    $seller = $this->seller();

    expect($this->listing($seller)->isPurchasable())->toBeTrue()
        ->and($this->listing($seller, ['status' => ListingStatus::Archived])->isPurchasable())->toBeFalse()
        ->and($this->listing($seller, ['status' => ListingStatus::ForSale, 'quantity' => 0])->isPurchasable())->toBeFalse();
});

it('counts its events by type for a listing already in hand', function (): void {
    $listing = $this->listing($this->seller());
    $recordListingEvent = app(RecordListingEvent::class);
    $recordListingEvent($listing, null, ListingEventType::View, $this->moment('2026-08-20 09:00:00'));
    $recordListingEvent($listing, null, ListingEventType::View, $this->moment('2026-08-20 10:00:00'));
    $recordListingEvent($listing, null, ListingEventType::Favorite, $this->moment('2026-08-20 11:00:00'));

    $loaded = $listing->loadEventCounts();

    expect($loaded->views_count)->toBe(2)
        ->and($loaded->favorites_count)->toBe(1)
        ->and($loaded->cart_adds_count)->toBe(0);
});

it('counts events by type for every listing in a collection at once', function (): void {
    $seller = $this->seller();
    $counted = $this->listing($seller);
    $uncounted = $this->listing($seller);
    $recordListingEvent = app(RecordListingEvent::class);
    $recordListingEvent($counted, null, ListingEventType::View, $this->moment('2026-08-20 09:00:00'));
    $recordListingEvent($counted, null, ListingEventType::CartAdd, $this->moment('2026-08-20 10:00:00'));

    $listings = Listing::attachEventCounts(Listing::query()->orderBy('id')->get());

    $countedRow = $listings->firstWhere('id', $counted->id);
    $uncountedRow = $listings->firstWhere('id', $uncounted->id);
    assert($countedRow instanceof Listing);
    assert($uncountedRow instanceof Listing);
    expect($countedRow->views_count)->toBe(1)
        ->and($countedRow->favorites_count)->toBe(0)
        ->and($countedRow->cart_adds_count)->toBe(1)
        ->and($uncountedRow->views_count)->toBe(0)
        ->and($uncountedRow->favorites_count)->toBe(0)
        ->and($uncountedRow->cart_adds_count)->toBe(0);
});

it('reads its price as money', function (): void {
    $listing = $this->listing($this->seller(), ['price_cents' => 45000]);

    expect($listing->price()->format())->toBe('$450.00');
});

it('owns its own price and stock until it offers a choice or breaks into serialized pieces', function (): void {
    $listing = $this->listing($this->seller());
    $withChoice = $this->listing($this->seller());
    OptionAxis::factory()->create(['listing_id' => $withChoice->id]);
    $withPieces = $this->listing($this->seller());
    Variant::factory()->serialized()->create(['listing_id' => $withPieces->id, 'combo_key' => 'a']);

    expect($listing->hasOwnPriceAndStock())->toBeTrue()
        ->and($withChoice->hasOwnPriceAndStock())->toBeFalse()
        ->and($withPieces->hasOwnPriceAndStock())->toBeFalse();
});

it('renders a placeholder image when there is no upload', function (): void {
    $listing = $this->listing($this->seller(), ['title' => 'Blue Heron']);

    expect($listing->imageUrl())->toStartWith('data:image/svg+xml;base64,');
});

it('serves an uploaded image from the public disk', function (): void {
    $listing = $this->listing($this->seller());
    $this->listingImage($listing, ['path' => 'listings/heron.png']);

    expect($listing->imageUrl())->toEndWith('/storage/listings/heron.png');
});

it('reads the lowest-position image as the cover', function (): void {
    $listing = $this->listing($this->seller());
    $this->listingImage($listing, ['path' => 'listings/second.png', 'position' => 1]);
    $this->listingImage($listing, ['path' => 'listings/first.png', 'position' => 0]);

    expect($listing->imageUrl())->toEndWith('/storage/listings/first.png');
});

it('reads the cover from an eager-loaded images relation rather than a fresh query', function (): void {
    $listing = $this->listing($this->seller());
    $this->listingImage($listing, ['path' => 'listings/second.png', 'position' => 1]);
    $this->listingImage($listing, ['path' => 'listings/first.png', 'position' => 0]);

    $loaded = Listing::query()
        ->with(['images' => fn (Relation $images): Relation => $images->orderBy('position')])
        ->findOrFail($listing->id);

    expect($loaded->relationLoaded('images'))->toBeTrue();

    $queries = 0;
    DB::listen(function () use (&$queries): void {
        $queries++;
    });

    expect($loaded->imageUrl())->toEndWith('/storage/listings/first.png')
        ->and($queries)->toBe(0);
});

it('renders a placeholder from an eager-loaded but empty images relation', function (): void {
    $listing = $this->listing($this->seller(), ['title' => 'Blue Heron']);

    $loaded = Listing::query()
        ->with(['images' => fn (Relation $images): Relation => $images->orderBy('position')])
        ->findOrFail($listing->id);

    expect($loaded->imageUrl())->toStartWith('data:image/svg+xml;base64,');
});

it('sells items off its quantity', function (): void {
    $listing = $this->listing($this->seller(), ['quantity' => 3]);

    $listing->sell(2);

    expect($listing->refresh()->quantity)->toBe(1)
        ->and($listing)->toHaveStatus(ListingStatus::ForSale);
});

it('is sold once the last item goes', function (): void {
    $listing = $this->listing($this->seller(), ['quantity' => 1]);

    $listing->sell(1);

    expect($listing->refresh()->quantity)->toBe(0)
        ->and($listing)->toHaveStatus(ListingStatus::Sold);
});

it('refuses a sale of more than it holds, and writes nothing', function (): void {
    $listing = $this->listing($this->seller(), ['quantity' => 1]);

    expect(fn () => $listing->sell(2))->toThrow(DomainException::class)
        ->and($listing->refresh()->quantity)->toBe(1);
});

it('refuses a sale of a listing that left the storefront', function (): void {
    $listing = $this->listing($this->seller(), ['status' => ListingStatus::Archived]);

    expect(fn () => $listing->sell(1))->toThrow(DomainException::class, 'is no longer for sale');
});

it('restocks items a sale took', function (): void {
    $listing = $this->listing($this->seller(), ['quantity' => 1]);
    $listing->sell(1);

    $listing->restock(1);

    expect($listing->refresh()->quantity)->toBe(1)
        ->and($listing)->toHaveStatus(ListingStatus::ForSale);
});

it('DSGN-003 leaves a made-to-order quantity null through a sale', function (): void {
    $listing = $this->listing($this->seller(), ['quantity' => null]);

    $listing->sell(3);

    expect($listing->refresh()->quantity)->toBeNull()
        ->and($listing)->toHaveStatus(ListingStatus::ForSale);
});

it('DSGN-003 leaves a made-to-order quantity null through a restock', function (): void {
    $listing = $this->listing($this->seller(), ['quantity' => null]);
    $listing->sell(1);

    $listing->restock(1);

    expect($listing->refresh()->quantity)->toBeNull();
});

it('DSGN-003 labels a made-to-order listing rather than a bare zero', function (): void {
    $listing = $this->listing($this->seller(), ['quantity' => null]);
    $tracked = $this->listing($this->seller(), ['quantity' => 4]);

    expect($listing->quantityLabel())->toBe('Made to order')
        ->and($tracked->quantityLabel())->toBe('4');
});

it('moves through an allowed status transition', function (ListingStatus $from, ListingStatus $to): void {
    $listing = $this->listing($this->seller(), ['status' => $from]);

    $listing->changeStatusTo($to);

    expect($listing)->toHaveStatus($to);
})->with([
    'draft to for sale' => [ListingStatus::Draft, ListingStatus::ForSale],
    'for sale to archived' => [ListingStatus::ForSale, ListingStatus::Archived],
]);

it('refuses a status transition the lifecycle does not allow, and leaves the row alone', function (): void {
    $listing = $this->listing($this->seller(), ['status' => ListingStatus::Draft]);

    expect(fn () => $listing->changeStatusTo(ListingStatus::Sold))->toThrow(DomainException::class)
        ->and($listing)->toHaveStatus(ListingStatus::Draft);
});

it('reads false for an axis-free listing regardless of its one legacy variant', function (): void {
    $listing = $this->listing($this->seller());
    Variant::factory()->create(['listing_id' => $listing->id, 'combo_key' => '']);

    expect($listing->everyVariantCombinationExists())->toBeFalse();
});

it('reads false while a combination remains unfilled', function (): void {
    $listing = $this->listing($this->seller());
    $axis = OptionAxis::factory()->create(['listing_id' => $listing->id]);
    OptionValue::factory()->create(['axis_id' => $axis->id]);
    OptionValue::factory()->create(['axis_id' => $axis->id]);

    expect($listing->everyVariantCombinationExists())->toBeFalse();
});

it('reads true once every combination has a variant row', function (): void {
    $listing = $this->listing($this->seller());
    $axis = OptionAxis::factory()->create(['listing_id' => $listing->id]);
    $only = OptionValue::factory()->create(['axis_id' => $axis->id]);
    Variant::factory()->create(['listing_id' => $listing->id, 'combo_key' => $only->id]);

    expect($listing->everyVariantCombinationExists())->toBeTrue();
});

it('narrows by status and by seller, with a null filter adding no clause', function (): void {
    $sellerA = $this->seller('Blue Kiln Studio');
    $sellerB = $this->seller('Rye Press');
    $forSale = $this->listing($sellerA);
    $draft = $this->listing($sellerA, ['status' => ListingStatus::Draft]);
    $this->listing($sellerB);

    expect(Listing::query()->ofStatus(ListingStatus::Draft)->pluck('id')->all())->toBe([$draft->id])
        ->and(Listing::query()->ofStatus(null)->count())->toBe(3)
        ->and(Listing::query()->ofSeller($sellerA->id)->pluck('id')->all())->toEqualCanonicalizing([$forSale->id, $draft->id])
        ->and(Listing::query()->ofSeller(null)->count())->toBe(3);
});

it('counts every status across every seller\'s listings, in one row each', function (): void {
    $this->listing($this->seller(), ['status' => ListingStatus::Draft]);
    $this->listing($this->seller(), ['status' => ListingStatus::ForSale]);
    $this->listing($this->seller(), ['status' => ListingStatus::ForSale]);

    expect(Listing::platformCountsByStatus())->toEqualCanonicalizing([
        ListingStatus::Draft->value => 1,
        ListingStatus::ForSale->value => 2,
    ]);
});
