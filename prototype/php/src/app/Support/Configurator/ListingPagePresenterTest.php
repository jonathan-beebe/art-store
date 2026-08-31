<?php

declare(strict_types=1);

namespace App\Support\Configurator;

use App\Actions\Configurator\AddOptionValue;
use App\Actions\Configurator\CreateOptionAxis;
use App\Actions\Configurator\GenerateVariants;
use App\Domain\Configurator\PriceBreakdown;
use App\Models\DescriptionSection;
use App\Models\Listing;
use App\Models\ListingImage;
use Illuminate\Http\Request;

it('assembles the listing page data, itemized for its own view keys', function (): void {
    $seller = $this->seller();
    $listing = $this->listing($seller, ['title' => 'Harbour at Dawn']);
    $this->attribute($listing, 'Material', 'Walnut');
    $visitor = $this->anonymousCustomer();

    $data = ListingPagePresenter::forShop($listing, $visitor, Request::create('/art/'.$listing->slug));

    $renderedListing = $data['listing'];
    assert($renderedListing instanceof Listing);

    expect($renderedListing->is($listing))->toBeTrue()
        ->and($data['isPurchasable'])->toBeTrue()
        ->and($data['isFavorited'])->toBeFalse()
        ->and($data['hasConfigurator'])->toBeFalse()
        ->and($data['configuration'])->toBeNull()
        ->and($data['highlights'])->toBe([['name' => 'Material', 'values' => ['Walnut']]])
        ->and($data['focusId'])->toBeNull();
});

it('prices a configured listing concretely and reports the focused control', function (): void {
    $seller = $this->seller();
    $listing = $this->listing($seller);
    $metal = app(CreateOptionAxis::class)($listing, 'Metal');
    app(AddOptionValue::class)($metal, 'Gold', 0, isDefault: true);
    app(GenerateVariants::class)($listing);
    $visitor = $this->anonymousCustomer();

    $data = ListingPagePresenter::forShop($listing, $visitor, Request::create('/art/'.$listing->slug.'?focus=axis-'.$metal->id));

    $configuration = $data['configuration'];
    assert($configuration instanceof ListingConfiguration);

    expect($data['hasConfigurator'])->toBeTrue()
        ->and($configuration->breakdown)->toBeInstanceOf(PriceBreakdown::class)
        ->and($data['focusId'])->toBe('axis-'.$metal->id);
});

it('orders eager-loaded description sections and images by position', function (): void {
    $seller = $this->seller();
    $listing = $this->listing($seller);
    DescriptionSection::factory()->create(['listing_id' => $listing->id, 'position' => 2, 'title' => 'Second']);
    DescriptionSection::factory()->create(['listing_id' => $listing->id, 'position' => 1, 'title' => 'First']);
    ListingImage::factory()->create(['listing_id' => $listing->id, 'position' => 2]);
    ListingImage::factory()->create(['listing_id' => $listing->id, 'position' => 1]);
    $visitor = $this->anonymousCustomer();

    $data = ListingPagePresenter::forShop($listing, $visitor, Request::create('/art/'.$listing->slug));

    $renderedListing = $data['listing'];
    assert($renderedListing instanceof Listing);

    expect($renderedListing->descriptionSections->pluck('title')->all())->toBe(['First', 'Second'])
        ->and($renderedListing->images->pluck('position')->all())->toBe([1, 2]);
});
