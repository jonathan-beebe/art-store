<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Category;
use App\Models\CategoryProperty;
use App\Models\Property;

it('seeds a category tree with grants exercising attribute, axis, and required', function (): void {
    $this->seed(TaxonomySeeder::class);

    expect(Category::count())->toBe(7)
        ->and(Category::whereNull('parent_id')->count())->toBe(5)
        ->and(Category::where('name', 'Rings')->sole()->parent()->first()?->name)->toBe('Jewelry');

    $ringSizeGrant = CategoryProperty::query()
        ->whereHas('category', fn ($q) => $q->where('name', 'Rings'))
        ->whereHas('property', fn ($q) => $q->where('name', 'Ring Size'))
        ->sole();

    expect($ringSizeGrant->usable_as_axis)->toBeTrue()
        ->and($ringSizeGrant->required)->toBeTrue()
        ->and($ringSizeGrant->usable_as_attribute)->toBeFalse();

    $homeGoodsMediumGrant = CategoryProperty::query()
        ->whereHas('category', fn ($q) => $q->where('name', 'Home Goods'))
        ->whereHas('property', fn ($q) => $q->where('name', 'Medium'))
        ->sole();

    expect($homeGoodsMediumGrant->usable_as_attribute)->toBeTrue()
        ->and($homeGoodsMediumGrant->usable_as_axis)->toBeFalse()
        ->and($homeGoodsMediumGrant->multivalued)->toBeTrue();

    $furnitureMediumGrant = CategoryProperty::query()
        ->whereHas('category', fn ($q) => $q->where('name', 'Furniture'))
        ->whereHas('property', fn ($q) => $q->where('name', 'Medium'))
        ->sole();

    expect($furnitureMediumGrant->multivalued)->toBeFalse();

    $mediumGrant = CategoryProperty::query()
        ->whereHas('category', fn ($q) => $q->where('name', 'Art'))
        ->whereHas('property', fn ($q) => $q->where('name', 'Medium'))
        ->sole();

    expect($mediumGrant->usable_as_attribute)->toBeTrue()
        ->and($mediumGrant->required)->toBeTrue();

    expect(Property::where('name', 'Metal')->sole()->values()->pluck('label')->all())
        ->toBe(['Gold', 'Silver', 'Rose Gold']);
});

it('gives Medium one high-level vocabulary and grants it as an attribute everywhere a listing is seeded', function (): void {
    $this->seed(TaxonomySeeder::class);

    expect(Property::where('name', 'Medium')->sole()->values()->pluck('label')->sort()->values()->all())
        ->toBe([
            'Apparel', 'Ceramic', 'Curio', 'Jewelry', 'Metal', 'Painting',
            'Paper', 'Photograph', 'Plant', 'Print', 'Publication', 'Sculpture', 'Textile',
            'Wood',
        ]);

    foreach (['Art', 'Jewelry', 'Rings', 'Home Goods', 'Furniture', 'Apparel', 'Stationery'] as $categoryName) {
        $grant = CategoryProperty::query()
            ->whereHas('category', fn ($q) => $q->where('name', $categoryName))
            ->whereHas('property', fn ($q) => $q->where('name', 'Medium'))
            ->sole();

        expect($grant->usable_as_attribute)->toBeTrue();
    }
});

it('changes nothing on a second run', function (): void {
    $this->seed(TaxonomySeeder::class);
    $this->seed(TaxonomySeeder::class);

    expect(Category::count())->toBe(7)
        ->and(Property::count())->toBe(6);
});
