<?php

declare(strict_types=1);

namespace App\Http\Requests\Seller;

use App\Models\Modifier;
use App\Models\OptionAxis;
use App\Models\OptionValue;

it('reads an empty selection as no option values', function (): void {
    $request = ModifierScopeRequest::create('/whatever', 'POST');

    expect($request->optionValues())->toBe([]);
});

it('refuses an option value that belongs to a different listing’s axis', function (): void {
    $seller = test()->seller();
    $listing = test()->listing($seller);
    $otherListing = test()->listing($seller);
    $otherAxis = OptionAxis::factory()->create(['listing_id' => $otherListing->id]);
    $otherValue = OptionValue::factory()->create(['axis_id' => $otherAxis->id]);
    $modifier = Modifier::factory()->create(['listing_id' => $listing->id]);

    $response = test()->actingAs($seller, 'seller')->post("/seller/listings/{$listing->id}/modifiers/{$modifier->id}/scope", [
        'option_value_id' => [$otherValue->id],
    ]);

    $response->assertSessionHasErrors('option_value_id.0');
});

it('reads the always radio as no option values even with boxes still checked', function (): void {
    $seller = test()->seller();
    $listing = test()->listing($seller);
    $axis = \App\Models\OptionAxis::factory()->create(['listing_id' => $listing->id]);
    $value = \App\Models\OptionValue::factory()->create(['axis_id' => $axis->id]);
    $request = ModifierScopeRequest::create('/whatever', 'POST', ['when' => 'always', 'option_value_id' => [$value->id]]);

    expect($request->optionValues())->toBe([]);
});
