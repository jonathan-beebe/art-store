<?php

declare(strict_types=1);

use App\Models\OptionAxis;
use App\Models\OptionValue;
use App\Models\Variant;
use App\Models\VariantOption;

it('refuses a malformed price override', function (): void {
    $seller = $this->seller();
    $listing = $this->listing($seller);

    $response = $this->actingAs($seller, 'seller')->post("/seller/listings/{$listing->id}/variants", [
        'quantity' => 1,
        'price_override' => 'lots',
    ]);

    $response->assertSessionHasErrors('price_override');
});

it('refuses a combination that already has a variant row, naming it', function (): void {
    $seller = $this->seller();
    $listing = $this->listing($seller);
    $axis = OptionAxis::factory()->create(['listing_id' => $listing->id, 'name' => 'Metal']);
    $value = OptionValue::factory()->create(['axis_id' => $axis->id, 'label' => 'Gold']);
    $variant = Variant::factory()->create(['listing_id' => $listing->id, 'combo_key' => $value->id]);
    VariantOption::factory()->create(['variant_id' => $variant->id, 'axis_id' => $axis->id, 'option_value_id' => $value->id]);

    $response = $this->actingAs($seller, 'seller')->post("/seller/listings/{$listing->id}/variants", [
        'option_value_id' => [$axis->id => $value->id],
        'quantity' => 1,
    ]);

    $response->assertSessionHasErrors('option_value_id');
    $response->assertSessionHasErrors(['option_value_id' => 'Gold already exists — edit its row in the grid above.']);
    expect(Variant::where('listing_id', $listing->id)->count())->toBe(1);
});

it('skips the combination rule rather than 500 when an axis value does not exist at all', function (): void {
    $seller = $this->seller();
    $listing = $this->listing($seller);
    $axis = OptionAxis::factory()->create(['listing_id' => $listing->id]);

    $response = $this->actingAs($seller, 'seller')->post("/seller/listings/{$listing->id}/variants", [
        'option_value_id' => [$axis->id => 'not-a-real-option-value'],
        'quantity' => 1,
    ]);

    $response->assertSessionHasErrors("option_value_id.{$axis->id}");
    $response->assertStatus(302);
    expect(Variant::where('listing_id', $listing->id)->count())->toBe(0);
});
