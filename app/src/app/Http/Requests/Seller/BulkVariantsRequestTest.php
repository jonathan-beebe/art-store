<?php

declare(strict_types=1);

namespace App\Http\Requests\Seller;

use App\Models\OptionAxis;
use App\Models\OptionValue;

it('refuses a missing option value or enabled flag', function (): void {
    $seller = $this->seller();
    $listing = $this->listing($seller);

    $response = $this->actingAs($seller, 'seller')->post("/seller/listings/{$listing->id}/variants/bulk", []);

    $response->assertSessionHasErrors(['option_value_id', 'enabled']);
});

it('refuses an option value that belongs to a different listing’s axis', function (): void {
    $seller = $this->seller();
    $listing = $this->listing($seller);
    $otherListing = $this->listing($seller);
    $otherAxis = OptionAxis::factory()->create(['listing_id' => $otherListing->id]);
    $otherValue = OptionValue::factory()->create(['axis_id' => $otherAxis->id]);

    $response = $this->actingAs($seller, 'seller')->post("/seller/listings/{$listing->id}/variants/bulk", [
        'option_value_id' => $otherValue->id,
        'enabled' => '1',
    ]);

    $response->assertSessionHasErrors('option_value_id');
});
