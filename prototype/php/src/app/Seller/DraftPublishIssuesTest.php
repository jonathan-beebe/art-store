<?php

declare(strict_types=1);

namespace App\Seller;

use App\Domain\Configurator\ConfiguratorPublishValidation;
use App\Domain\Configurator\PublishIssue;
use App\Domain\Listings\ListingStatus;
use App\Models\Category;
use App\Models\CategoryProperty;
use App\Models\Listing;
use App\Models\ListingAttribute;
use App\Models\Modifier;
use App\Models\OptionAxis;
use App\Models\OptionValue;
use App\Models\Property;
use App\Models\PropertyValue;
use App\Models\Variant;
use Database\Seeders\ConfiguratorArchetypeSeeder;
use Database\Seeders\TaxonomySeeder;

/**
 * @param  list<PublishIssue>  $issues
 * @return list<string>
 */
function issueCodes(array $issues): array
{
    return array_map(fn (PublishIssue $issue): string => $issue->code, $issues);
}

/**
 * @param  list<PublishIssue>  $issues
 * @return list<array{string, string, string|null}>
 */
function issueTuples(array $issues): array
{
    return array_map(fn (PublishIssue $issue): array => [$issue->code, $issue->message, $issue->subjectId], $issues);
}

it('reads the same issues publishIssues() reads, for one draft with an unfinished variant', function (): void {
    $listing = $this->listing($this->seller(), ['status' => ListingStatus::Draft]);
    $axis = OptionAxis::factory()->create(['listing_id' => $listing->id]);
    OptionValue::factory()->create(['axis_id' => $axis->id]);
    Variant::factory()->create(['listing_id' => $listing->id]);

    $batched = DraftPublishIssues::forListings(collect([$listing]));

    expect(issueCodes($batched[$listing->id]))->toBe(issueCodes($listing->publishIssues()))
        ->and(issueCodes($batched[$listing->id]))->toBe(['variant_missing_axis_value']);
});

it('reads too many modifiers among a batch of drafts', function (): void {
    $listing = $this->listing($this->seller(), ['status' => ListingStatus::Draft]);
    Modifier::factory()->count(ConfiguratorPublishValidation::MAX_MODIFIERS + 1)->create(['listing_id' => $listing->id]);

    $batched = DraftPublishIssues::forListings(collect([$listing]));

    expect(issueCodes($batched[$listing->id]))->toBe(['too_many_modifiers']);
});

it('reads a required attribute with no value across a batch', function (): void {
    $category = Category::factory()->create();
    $property = Property::factory()->create();
    CategoryProperty::factory()->create([
        'category_id' => $category->id,
        'property_id' => $property->id,
        'usable_as_attribute' => true,
        'required' => true,
    ]);
    $missing = $this->listing($this->seller(), ['status' => ListingStatus::Draft, 'category_id' => $category->id]);
    $value = PropertyValue::factory()->create(['property_id' => $property->id]);
    $satisfied = $this->listing($this->seller(), ['status' => ListingStatus::Draft, 'category_id' => $category->id]);
    ListingAttribute::factory()->create([
        'listing_id' => $satisfied->id,
        'property_id' => $property->id,
        'property_value_id' => $value->id,
    ]);

    $batched = DraftPublishIssues::forListings(collect([$missing, $satisfied]));

    expect(issueCodes($batched[$missing->id]))->toBe(['missing_required_attribute'])
        ->and($batched[$satisfied->id])->toBe([]);
});

it('leaves a listing that is not a draft with no entry', function (): void {
    $forSale = $this->listing($this->seller(), ['status' => ListingStatus::ForSale]);

    expect(DraftPublishIssues::forListings(collect([$forSale])))->toBe([]);
});

it('gives an empty listing with no configurator data no issues', function (): void {
    $listing = $this->listing($this->seller(), ['status' => ListingStatus::Draft]);

    expect(DraftPublishIssues::forListings(collect([$listing])))->toBe([$listing->id => []]);
});

it('costs the same number of queries whatever the count of drafts in the batch', function (int $count): void {
    $seller = $this->seller();

    /** @var \Illuminate\Support\Collection<int, Listing> $listings */
    $listings = collect();

    for ($i = 0; $i < $count; $i++) {
        $listing = $this->listing($seller, ['status' => ListingStatus::Draft]);
        $axis = OptionAxis::factory()->create(['listing_id' => $listing->id]);
        OptionValue::factory()->create(['axis_id' => $axis->id]);
        Variant::factory()->create(['listing_id' => $listing->id]);
        $listings->push($listing);
    }

    $this->expectsDatabaseQueryCount(10);

    DraftPublishIssues::forListings($listings);
})->with([
    'two drafts' => [2],
    'five drafts' => [5],
]);

it('agrees with publishIssues() on a configurator archetype, issue for issue', function (string $title): void {
    $this->seed(TaxonomySeeder::class);
    $this->seed(ConfiguratorArchetypeSeeder::class);

    $listing = Listing::where('title', $title)->sole();
    $listing->status = ListingStatus::Draft;

    $batched = DraftPublishIssues::forListings(collect([$listing]));

    expect(issueTuples($batched[$listing->id] ?? []))->toBe(issueTuples($listing->publishIssues()));
})->with([
    'the plain print, zero axes' => ['Quidditch Pitch at Dawn, 8x10 Print'],
    'the engraved ring, surcharges and scoped modifiers' => ['Engraved House Signet Ring'],
    'the personalized mug, a scoped text modifier' => ['Three Broomsticks Stoneware Mug'],
    'the pod tee, size-tier surcharges' => ['Line Art Kneazle Tee'],
    'the walnut table, sparse price-override variants' => ['Live-Edge Great Hall Dining Table'],
    'the vintage candlesticks, a serialized variant with units' => ['Great Hall Brass Candlesticks, Individually Listed'],
    'the wedding invitations, a priced modifier and quantity breaks' => ['Letterpress Yule Ball Invitations'],
    'the pet portrait, a three-axis cross product' => ['Custom Patronus Portrait'],
    'the sunset print, a standalone-priced axis' => ['The Burrow at Sunset, Fine Art Print'],
]);
