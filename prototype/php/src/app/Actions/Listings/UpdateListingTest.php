<?php

declare(strict_types=1);

namespace App\Actions\Listings;

use App\Domain\Listings\ListingDraft;
use App\Domain\Listings\ListingStatus;
use App\Domain\Money\Money;
use App\Models\Category;
use App\Models\CategoryProperty;
use App\Models\Listing;
use App\Models\ListingAttribute;
use App\Models\Property;
use App\Models\PropertyValue;

$draft = fn (): ListingDraft => ListingDraft::of(
    'Harbour at Dawn',
    'Oil on linen.',
    '12 x 16 in',
    Money::fromCents(9900),
    1,
);

it('writes the drafted fields', function () use ($draft): void {
    $listing = $this->listing($this->seller(), ['title' => 'Old title', 'price_cents' => 100]);

    app(UpdateListing::class)($listing, $draft());

    expect($listing->refresh()->title)->toBe('Harbour at Dawn')
        ->and($listing->price_cents)->toBe(9900);
});

it('keeps the slug a renamed listing was shared under', function () use ($draft): void {
    $listing = $this->listing($this->seller(), ['title' => 'Old title', 'slug' => 'old-title']);

    app(UpdateListing::class)($listing, $draft());

    expect($listing->refresh()->slug)->toBe('old-title');
});

it('keeps the status the listing already had', function () use ($draft): void {
    $listing = $this->listing($this->seller(), ['status' => ListingStatus::ForSale]);

    app(UpdateListing::class)($listing, $draft());

    expect($listing)->toHaveStatus(ListingStatus::ForSale);
});

it('DSGN-002 never touches a listing’s images — that save belongs to the Images screen', function () use ($draft): void {
    $listing = $this->listing($this->seller());
    $this->listingImage($listing, ['path' => 'listings/kept.jpg']);

    app(UpdateListing::class)($listing, $draft());

    expect($listing->images()->sole()->path)->toBe('listings/kept.jpg');
});

it('assigns the category a seller picked', function (): void {
    $listing = $this->listing($this->seller());
    $category = Category::factory()->create();

    app(UpdateListing::class)($listing, ListingDraft::of('Harbour at Dawn', 'Oil on linen.', '12 x 16 in', Money::fromCents(9900), 1, $category->id));

    expect($listing->refresh()->category_id)->toBe($category->id);
});

it('prunes attribute rows the new category does not grant', function (): void {
    $oldCategory = Category::factory()->create();
    $newCategory = Category::factory()->create();
    $property = Property::factory()->create();
    $value = PropertyValue::factory()->create(['property_id' => $property->id]);
    CategoryProperty::factory()->create([
        'category_id' => $oldCategory->id,
        'property_id' => $property->id,
        'usable_as_attribute' => true,
    ]);
    $listing = $this->listing($this->seller(), ['category_id' => $oldCategory->id]);
    ListingAttribute::factory()->create([
        'listing_id' => $listing->id,
        'property_id' => $property->id,
        'property_value_id' => $value->id,
    ]);

    app(UpdateListing::class)($listing, ListingDraft::of('Harbour at Dawn', 'Oil on linen.', '12 x 16 in', Money::fromCents(9900), 1, $newCategory->id));

    expect($listing->listingAttributes()->count())->toBe(0);
});

it('keeps attribute rows the new category still grants', function (): void {
    $property = Property::factory()->create();
    $value = PropertyValue::factory()->create(['property_id' => $property->id]);
    $category = Category::factory()->create();
    CategoryProperty::factory()->create([
        'category_id' => $category->id,
        'property_id' => $property->id,
        'usable_as_attribute' => true,
    ]);
    $listing = $this->listing($this->seller(), ['category_id' => $category->id]);
    ListingAttribute::factory()->create([
        'listing_id' => $listing->id,
        'property_id' => $property->id,
        'property_value_id' => $value->id,
    ]);

    // Re-submitting the same category is not a change — the attribute stays.
    app(UpdateListing::class)($listing, ListingDraft::of('Harbour at Dawn', 'Oil on linen.', '12 x 16 in', Money::fromCents(9900), 1, $category->id));

    expect($listing->listingAttributes()->count())->toBe(1);
});

it('clears every listing attribute when the category is cleared to none', function (): void {
    $category = Category::factory()->create();
    $property = Property::factory()->create();
    $value = PropertyValue::factory()->create(['property_id' => $property->id]);
    CategoryProperty::factory()->create([
        'category_id' => $category->id,
        'property_id' => $property->id,
        'usable_as_attribute' => true,
    ]);
    $listing = $this->listing($this->seller(), ['category_id' => $category->id]);
    ListingAttribute::factory()->create([
        'listing_id' => $listing->id,
        'property_id' => $property->id,
        'property_value_id' => $value->id,
    ]);

    app(UpdateListing::class)($listing, ListingDraft::of('Harbour at Dawn', 'Oil on linen.', '12 x 16 in', Money::fromCents(9900), 1, null));

    expect($listing->refresh()->category_id)->toBeNull()
        ->and($listing->listingAttributes()->count())->toBe(0);
});

it('leaves other listings alone', function () use ($draft): void {
    $seller = $this->seller();
    $listing = $this->listing($seller, ['title' => 'Mine']);
    $other = $this->listing($seller, ['title' => 'Untouched']);

    app(UpdateListing::class)($listing, $draft());

    expect(Listing::findOrFail($other->id)->title)->toBe('Untouched');
});
