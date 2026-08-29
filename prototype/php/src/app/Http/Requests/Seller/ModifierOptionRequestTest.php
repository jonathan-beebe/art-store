<?php

declare(strict_types=1);

namespace App\Http\Requests\Seller;

use App\Models\Modifier;
use App\Models\ModifierOption;

it('reads a signed, dollar-formatted price the same way it renders one', function (): void {
    $seller = $this->seller();
    $listing = $this->listing($seller);
    $modifier = Modifier::factory()->select()->create(['listing_id' => $listing->id]);

    $this->actingAs($seller, 'seller')->post("/seller/listings/{$listing->id}/modifiers/{$modifier->id}/options", [
        'label' => 'Gold leaf',
        'add_on_price' => '+$1.50',
        'position' => 0,
    ]);

    $option = ModifierOption::where('modifier_id', $modifier->id)->sole();
    expect($option->add_on_price_cents)->toBe(150);
});

it('reads an em dash as no price difference', function (): void {
    $seller = $this->seller();
    $listing = $this->listing($seller);
    $modifier = Modifier::factory()->select()->create(['listing_id' => $listing->id]);

    $this->actingAs($seller, 'seller')->post("/seller/listings/{$listing->id}/modifiers/{$modifier->id}/options", [
        'label' => 'Black',
        'add_on_price' => '—',
        'position' => 0,
    ]);

    $option = ModifierOption::where('modifier_id', $modifier->id)->sole();
    expect($option->add_on_price_cents)->toBe(0);
});

it('refuses a malformed add-on price', function (): void {
    $seller = $this->seller();
    $listing = $this->listing($seller);
    $modifier = Modifier::factory()->select()->create(['listing_id' => $listing->id]);

    $response = $this->actingAs($seller, 'seller')->post("/seller/listings/{$listing->id}/modifiers/{$modifier->id}/options", [
        'label' => 'Serif',
        'add_on_price' => 'lots',
        'position' => 0,
    ]);

    $response->assertSessionHasErrors('add_on_price');
});
