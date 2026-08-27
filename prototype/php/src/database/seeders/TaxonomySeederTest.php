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

    $materialGrant = CategoryProperty::query()
        ->whereHas('category', fn ($q) => $q->where('name', 'Home Goods'))
        ->whereHas('property', fn ($q) => $q->where('name', 'Material'))
        ->sole();

    expect($materialGrant->usable_as_attribute)->toBeTrue()
        ->and($materialGrant->usable_as_axis)->toBeFalse()
        ->and($materialGrant->multivalued)->toBeFalse();

    $furnitureMaterialGrant = CategoryProperty::query()
        ->whereHas('category', fn ($q) => $q->where('name', 'Furniture'))
        ->whereHas('property', fn ($q) => $q->where('name', 'Material'))
        ->sole();

    expect($furnitureMaterialGrant->multivalued)->toBeTrue();

    $mediumGrant = CategoryProperty::query()
        ->whereHas('category', fn ($q) => $q->where('name', 'Art'))
        ->whereHas('property', fn ($q) => $q->where('name', 'Medium'))
        ->sole();

    expect($mediumGrant->usable_as_attribute)->toBeTrue()
        ->and($mediumGrant->required)->toBeTrue();

    expect(Property::where('name', 'Metal')->sole()->values()->pluck('label')->all())
        ->toBe(['Gold', 'Silver', 'Rose Gold']);
});

it('changes nothing on a second run', function (): void {
    $this->seed(TaxonomySeeder::class);
    $this->seed(TaxonomySeeder::class);

    expect(Category::count())->toBe(7)
        ->and(Property::count())->toBe(7);
});
