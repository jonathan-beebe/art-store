<?php

declare(strict_types=1);

namespace App\Models;

it('belongs to a listing, an optional property, and lists its values', function (): void {
    $listing = $this->listing($this->seller());
    $property = Property::factory()->create();
    $axis = OptionAxis::factory()->create(['listing_id' => $listing->id, 'property_id' => $property->id]);
    OptionValue::factory()->count(2)->create(['axis_id' => $axis->id]);

    expect($axis->listing()->first()?->id)->toBe($listing->id)
        ->and($axis->property()->first()?->id)->toBe($property->id)
        ->and($axis->optionValues()->count())->toBe(2);
});
