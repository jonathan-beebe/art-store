<?php

declare(strict_types=1);

namespace App\Support\Configurator;

use App\Domain\Listings\ListingStatus;
use App\Models\Category;
use App\Models\Listing;
use App\Models\OptionAxis;
use App\Models\Variant;

it('carries the category tree and an empty publish-issue list for a listing not in draft', function (): void {
    Category::factory()->create(['name' => 'Jewelry']);
    $listing = Listing::factory()->create(['status' => ListingStatus::ForSale]);

    $data = ListingEditPageData::for($listing);
    /** @var \Illuminate\Support\Collection<int, Category> $categories */
    $categories = $data['categories'];

    expect($data['listing'])->toBe($listing)
        ->and($categories->pluck('name')->all())->toBe(['Jewelry'])
        ->and($data['publishIssues'])->toBe([]);
});

it('carries the drafts publish issues', function (): void {
    $listing = Listing::factory()->create(['status' => ListingStatus::Draft]);
    OptionAxis::factory()->create(['listing_id' => $listing->id]);
    Variant::factory()->create(['listing_id' => $listing->id]);

    $data = ListingEditPageData::for($listing);

    expect($data['publishIssues'])->not->toBe([]);
});
