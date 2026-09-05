<?php

declare(strict_types=1);

namespace App\Http\Requests\Seller;

use App\Models\OptionAxis;
use App\Models\OptionValue;

it('refuses a malformed price difference', function (): void {
    $seller = $this->seller();
    $listing = $this->listing($seller);
    $axis = OptionAxis::factory()->create(['listing_id' => $listing->id]);

    $response = $this->actingAs($seller, 'seller')->post("/seller/listings/{$listing->id}/option-axes/{$axis->id}/option-values", [
        'label' => 'Gold',
        'surcharge' => 'a lot',
        'position' => 0,
    ]);

    $response->assertSessionHasErrors('surcharge');
});

it('requires a price on a standalone axis, refusing a blank one', function (): void {
    $seller = $this->seller();
    $listing = $this->listing($seller);
    $axis = OptionAxis::factory()->standalone()->create(['listing_id' => $listing->id]);

    $response = $this->actingAs($seller, 'seller')->post("/seller/listings/{$listing->id}/option-axes/{$axis->id}/option-values", [
        'label' => '8x10',
        'price' => '',
        'position' => 0,
    ]);

    $response->assertSessionHasErrors('price');
    expect(OptionValue::where('axis_id', $axis->id)->count())->toBe(0);
});

it('refuses a malformed price on a standalone axis', function (): void {
    $seller = $this->seller();
    $listing = $this->listing($seller);
    $axis = OptionAxis::factory()->standalone()->create(['listing_id' => $listing->id]);

    $response = $this->actingAs($seller, 'seller')->post("/seller/listings/{$listing->id}/option-axes/{$axis->id}/option-values", [
        'label' => '8x10',
        'price' => 'a lot',
        'position' => 0,
    ]);

    $response->assertSessionHasErrors('price');
});

it('stores a standalone axis option’s price', function (): void {
    $seller = $this->seller();
    $listing = $this->listing($seller);
    $axis = OptionAxis::factory()->standalone()->create(['listing_id' => $listing->id]);

    $this->actingAs($seller, 'seller')->post("/seller/listings/{$listing->id}/option-axes/{$axis->id}/option-values", [
        'label' => '8x10',
        'price' => '18.00',
        'position' => 0,
    ]);

    $value = OptionValue::where('axis_id', $axis->id)->sole();
    expect($value->price_cents)->toBe(1800)
        ->and($value->surcharge_cents)->toBe(0);
});

it('ignores a price sent on an add-on axis', function (): void {
    $seller = $this->seller();
    $listing = $this->listing($seller);
    $axis = OptionAxis::factory()->addOn()->create(['listing_id' => $listing->id]);

    $response = $this->actingAs($seller, 'seller')->post("/seller/listings/{$listing->id}/option-axes/{$axis->id}/option-values", [
        'label' => 'Unframed',
        'price' => '18.00',
        'position' => 0,
    ]);

    $response->assertSessionDoesntHaveErrors('price');
    expect(OptionValue::where('axis_id', $axis->id)->sole()->price_cents)->toBeNull();
});

it('never requires a price on an add-on axis', function (): void {
    $seller = $this->seller();
    $listing = $this->listing($seller);
    $axis = OptionAxis::factory()->addOn()->create(['listing_id' => $listing->id]);

    $response = $this->actingAs($seller, 'seller')->post("/seller/listings/{$listing->id}/option-axes/{$axis->id}/option-values", [
        'label' => 'Unframed',
        'position' => 0,
    ]);

    $response->assertSessionDoesntHaveErrors('price');
    expect(OptionValue::where('axis_id', $axis->id)->sole()->price_cents)->toBeNull();
});
