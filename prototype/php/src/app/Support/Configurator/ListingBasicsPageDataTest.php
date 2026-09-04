<?php

declare(strict_types=1);

namespace App\Support\Configurator;

use App\Models\Category;
use App\Models\CategoryProperty;
use App\Models\FulfillmentFlow;
use App\Models\Listing;
use App\Models\OptionAxis;
use App\Models\Property;

it('carries the listing and its category tree', function (): void {
    Category::factory()->create(['name' => 'Jewelry']);
    $listing = Listing::factory()->create();

    $data = ListingBasicsPageData::for($listing);
    /** @var \Illuminate\Support\Collection<int, Category> $categories */
    $categories = $data['categories'];

    expect($data['listing'])->toBe($listing)
        ->and($categories->pluck('name')->all())->toBe(['Jewelry']);
});

it('carries the attribute grants and selections for the listings category', function (): void {
    $category = Category::factory()->create();
    $property = Property::factory()->create(['name' => 'Material']);
    CategoryProperty::factory()->create(['category_id' => $category->id, 'property_id' => $property->id, 'usable_as_attribute' => true]);
    $listing = Listing::factory()->create(['category_id' => $category->id]);

    $data = ListingBasicsPageData::for($listing);

    expect($data['attributeGrants'])->toHaveCount(1)
        ->and($data['listingAttributeSelections'])->toBe([]);
});

it('reports that an unconfigured listing still owns its price and stock', function (): void {
    $listing = Listing::factory()->create();

    expect(ListingBasicsPageData::for($listing)['hasOwnPriceAndStock'])->toBeTrue();
});

it('reports that a listing with a choice no longer owns its price and stock', function (): void {
    $listing = Listing::factory()->create();
    OptionAxis::factory()->create(['listing_id' => $listing->id]);

    expect(ListingBasicsPageData::for($listing)['hasOwnPriceAndStock'])->toBeFalse();
});

it('carries no workflows for a seller with none or one', function (int $flowCount): void {
    $listing = Listing::factory()->create();
    FulfillmentFlow::factory()->count($flowCount)->create(['seller_id' => $listing->seller_id]);

    expect(ListingBasicsPageData::for($listing)['workflows'])->toBeEmpty();
})->with([0, 1]);

it('carries every workflow, the default first, for a seller who holds more than one', function (): void {
    $listing = Listing::factory()->create();
    $second = FulfillmentFlow::factory()->create(['seller_id' => $listing->seller_id, 'name' => 'Framed pieces']);
    $default = FulfillmentFlow::factory()->isDefault()->create(['seller_id' => $listing->seller_id, 'name' => 'How I ship']);

    /** @var \Illuminate\Support\Collection<int, FulfillmentFlow> $workflows */
    $workflows = ListingBasicsPageData::for($listing)['workflows'];

    expect($workflows->pluck('id')->all())->toBe([$default->id, $second->id]);
});
