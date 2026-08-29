<?php

declare(strict_types=1);

namespace App\Models;

it('grants a property to a category, gated by usage', function (): void {
    $category = Category::factory()->create();
    $property = Property::factory()->create();
    $grant = CategoryProperty::factory()->usableAsAxis()->required()->create([
        'category_id' => $category->id,
        'property_id' => $property->id,
    ]);

    expect($grant->category()->first()?->id)->toBe($category->id)
        ->and($grant->property()->first()?->id)->toBe($property->id)
        ->and($grant->usable_as_axis)->toBeTrue()
        ->and($grant->required)->toBeTrue()
        ->and($grant->usable_as_attribute)->toBeTrue()
        ->and($grant->multivalued)->toBeFalse();
});
