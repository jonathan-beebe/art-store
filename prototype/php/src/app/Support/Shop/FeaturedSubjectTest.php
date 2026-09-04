<?php

declare(strict_types=1);

use App\Domain\Listings\ListingStatus;
use App\Models\Category;
use App\Models\Favorite;
use App\Support\Shop\FeaturedSubject;

it('resolves a configured listing', function (): void {
    config(['storefront.featured' => ['type' => 'listing', 'value' => 'harbour-at-dawn']]);
    $seller = $this->seller('Blue Kiln Studio');
    $listing = $this->listing($seller, [
        'title' => 'Harbour at Dawn',
        'slug' => 'harbour-at-dawn',
        'description' => 'Painted from the quay at low tide.',
        'price_cents' => 24500,
    ]);

    $subject = FeaturedSubject::resolve();
    expect($subject)->not->toBeNull();
    assert($subject instanceof FeaturedSubject);

    expect($subject->title)->toBe('Harbour at Dawn')
        ->and($subject->description)->toBe('Painted from the quay at low tide.')
        ->and($subject->imageUrl)->toBe($listing->imageUrl())
        ->and($subject->price)->toBe('$245.00')
        ->and($subject->byline)->toBe('Blue Kiln Studio')
        ->and($subject->ctaHref)->toBe(route('shop.listing', $listing))
        ->and($subject->ctaLabel)->toBe('See this piece');
});

it('answers null for a configured listing slug nothing carries', function (): void {
    config(['storefront.featured' => ['type' => 'listing', 'value' => 'missing-piece']]);

    expect(FeaturedSubject::resolve())->toBeNull();
});

it('answers null for a configured listing that is no longer for sale', function (): void {
    config(['storefront.featured' => ['type' => 'listing', 'value' => 'sold-vase']]);
    $this->listing($this->seller(), ['title' => 'Sold Vase', 'slug' => 'sold-vase', 'status' => ListingStatus::Sold, 'quantity' => 0]);

    expect(FeaturedSubject::resolve())->toBeNull();
});

it('resolves a configured category, covered by its best-favorited for-sale listing', function (): void {
    config(['storefront.featured' => ['type' => 'category', 'value' => 'jewelry']]);
    $jewelry = Category::factory()->create(['name' => 'Jewelry', 'path' => '/jewelry/']);
    $seller = $this->seller();
    $this->listing($seller, ['category_id' => $jewelry->id]);
    $favorited = $this->listing($seller, ['category_id' => $jewelry->id]);
    Favorite::factory()->create(['listing_id' => $favorited->id]);

    $subject = FeaturedSubject::resolve();
    expect($subject)->not->toBeNull();
    assert($subject instanceof FeaturedSubject);

    expect($subject->title)->toBe('Jewelry')
        ->and($subject->description)->toBe('2 pieces waiting to be discovered.')
        ->and($subject->imageUrl)->toBe($favorited->imageUrl())
        ->and($subject->price)->toBeNull()
        ->and($subject->byline)->toBeNull()
        ->and($subject->ctaHref)->toBe(route('shop.browse', ['categoryPath' => 'jewelry']))
        ->and($subject->ctaLabel)->toBe('Browse Jewelry');
});

it('answers null for a configured category path nothing carries', function (): void {
    config(['storefront.featured' => ['type' => 'category', 'value' => 'jewelry']]);

    expect(FeaturedSubject::resolve())->toBeNull();
});

it('answers null for a configured category marked not browsable', function (): void {
    config(['storefront.featured' => ['type' => 'category', 'value' => 'hidden-room']]);
    $hidden = Category::factory()->hidden()->create(['name' => 'Hidden Room', 'path' => '/hidden-room/']);
    $this->listing($this->seller(), ['category_id' => $hidden->id]);

    expect(FeaturedSubject::resolve())->toBeNull();
});

it('answers null for a configured category with no for-sale listing', function (): void {
    config(['storefront.featured' => ['type' => 'category', 'value' => 'jewelry']]);
    Category::factory()->create(['name' => 'Jewelry', 'path' => '/jewelry/']);

    expect(FeaturedSubject::resolve())->toBeNull();
});

it('answers null for an unrecognised featured type', function (): void {
    config(['storefront.featured' => ['type' => 'wizard', 'value' => 'anything']]);

    expect(FeaturedSubject::resolve())->toBeNull();
});

it('answers null when the configured value is missing rather than a slug or a path', function (): void {
    config(['storefront.featured' => ['type' => 'listing', 'value' => null]]);

    expect(FeaturedSubject::resolve())->toBeNull();
});

it('names the featured listing slug, and nothing when a category is featured', function (): void {
    config(['storefront.featured' => ['type' => 'listing', 'value' => 'harbour-at-dawn']]);
    expect(FeaturedSubject::listingSlug())->toBe('harbour-at-dawn');

    config(['storefront.featured' => ['type' => 'category', 'value' => 'paintings']]);
    expect(FeaturedSubject::listingSlug())->toBeNull();
});
