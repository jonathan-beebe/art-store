<?php

declare(strict_types=1);

namespace App\Configurator;

use App\Domain\Listings\ListingStatus;
use App\Models\Listing;
use App\Models\OptionAxis;
use App\Models\Variant;

it('carries the listing and an empty publish-issue list for a listing not in draft', function (): void {
    $listing = Listing::factory()->create(['status' => ListingStatus::ForSale]);

    $data = ListingEditPageData::for($listing);

    expect($data['listing'])->toBe($listing)
        ->and($data['publishIssues'])->toBe([]);
});

it('carries the basics and images summaries every listing has', function (): void {
    $listing = Listing::factory()->create();

    $data = ListingEditPageData::for($listing);

    expect($data['basics'])->toBe(ListingConfiguratorSummaries::basics($listing))
        ->and($data['imagesSummary'])->toBe(ListingConfiguratorSummaries::images($listing));
});

it('carries the drafts publish issues', function (): void {
    $listing = Listing::factory()->create(['status' => ListingStatus::Draft]);
    OptionAxis::factory()->create(['listing_id' => $listing->id]);
    Variant::factory()->create(['listing_id' => $listing->id]);

    $data = ListingEditPageData::for($listing);

    expect($data['publishIssues'])->not->toBe([]);
});

it('carries a null summary for every progressive-disclosure area on an unconfigured listing', function (): void {
    $listing = Listing::factory()->create();

    $data = ListingEditPageData::for($listing);

    expect($data['choicesSummary'])->toBeNull()
        ->and($data['questionsSummary'])->toBeNull()
        ->and($data['discountsLine'])->toBeNull()
        ->and($data['sectionsLine'])->toBeNull()
        ->and($data['piecesSummary'])->toBeNull();
});

it('carries a choices summary once the listing offers a choice', function (): void {
    $listing = Listing::factory()->create();
    OptionAxis::factory()->create(['listing_id' => $listing->id]);

    $data = ListingEditPageData::for($listing);

    expect($data['choicesSummary'])->not->toBeNull();
});
