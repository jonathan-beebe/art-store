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

it('reads a blank price difference as zero', function (): void {
    $seller = $this->seller();
    $listing = $this->listing($seller);
    $axis = OptionAxis::factory()->create(['listing_id' => $listing->id]);

    $this->actingAs($seller, 'seller')->post("/seller/listings/{$listing->id}/option-axes/{$axis->id}/option-values", [
        'label' => 'Gold',
        'surcharge' => '',
        'position' => 0,
    ]);

    expect(OptionValue::where('axis_id', $axis->id)->sole()->surcharge_cents)->toBe(0);
});

it('reads an em dash price difference as zero', function (): void {
    $seller = $this->seller();
    $listing = $this->listing($seller);
    $axis = OptionAxis::factory()->create(['listing_id' => $listing->id]);

    $this->actingAs($seller, 'seller')->post("/seller/listings/{$listing->id}/option-axes/{$axis->id}/option-values", [
        'label' => 'Gold',
        'surcharge' => '—',
        'position' => 0,
    ]);

    expect(OptionValue::where('axis_id', $axis->id)->sole()->surcharge_cents)->toBe(0);
});

it('reads a plus-and-dollar-signed price difference the same way it formats one', function (): void {
    $seller = $this->seller();
    $listing = $this->listing($seller);
    $axis = OptionAxis::factory()->create(['listing_id' => $listing->id]);

    $this->actingAs($seller, 'seller')->post("/seller/listings/{$listing->id}/option-axes/{$axis->id}/option-values", [
        'label' => 'Gold',
        'surcharge' => '+$6.00',
        'position' => 0,
    ]);

    expect(OptionValue::where('axis_id', $axis->id)->sole()->surcharge_cents)->toBe(600);
});
