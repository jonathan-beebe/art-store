<?php

declare(strict_types=1);

namespace App\Http\Requests\Seller;

it('reads an empty selection as no option values', function (): void {
    $request = ModifierScopeRequest::create('/whatever', 'POST');

    expect($request->optionValues())->toBe([]);
});

it('reads the always radio as no option values even with boxes still checked', function (): void {
    $seller = test()->seller();
    $listing = test()->listing($seller);
    $axis = \App\Models\OptionAxis::factory()->create(['listing_id' => $listing->id]);
    $value = \App\Models\OptionValue::factory()->create(['axis_id' => $axis->id]);
    $request = ModifierScopeRequest::create('/whatever', 'POST', ['when' => 'always', 'option_value_id' => [$value->id]]);

    expect($request->optionValues())->toBe([]);
});
