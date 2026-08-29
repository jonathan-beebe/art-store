<?php

declare(strict_types=1);

namespace App\Support\Configurator;

use App\Models\Category;
use App\Models\CategoryProperty;
use App\Models\Property;

it('offers no catalog properties for an uncategorized listing', function (): void {
    $listing = $this->listing($this->seller());
    Property::factory()->create(['name' => 'Somewhere Property']);

    expect(AxisPropertyOptions::for($listing))->toBeEmpty();
});

it('offers only the categorys usable-as-axis properties, ordered by name', function (): void {
    $category = Category::factory()->create();
    $wick = Property::factory()->create(['name' => 'Wick']);
    CategoryProperty::factory()->create(['category_id' => $category->id, 'property_id' => $wick->id, 'usable_as_axis' => true, 'usable_as_attribute' => false]);
    $waxType = Property::factory()->create(['name' => 'Wax type']);
    CategoryProperty::factory()->create(['category_id' => $category->id, 'property_id' => $waxType->id, 'usable_as_axis' => true, 'usable_as_attribute' => false]);
    $attributeOnly = Property::factory()->create(['name' => 'Material Only']);
    CategoryProperty::factory()->create(['category_id' => $category->id, 'property_id' => $attributeOnly->id, 'usable_as_axis' => false, 'usable_as_attribute' => true]);
    $listing = $this->listing($this->seller(), ['category_id' => $category->id]);

    expect(AxisPropertyOptions::for($listing)->pluck('name')->all())->toBe(['Wax type', 'Wick']);
});
