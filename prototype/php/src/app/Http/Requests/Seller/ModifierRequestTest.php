<?php

declare(strict_types=1);

namespace App\Http\Requests\Seller;

use App\Models\Modifier;

it('refuses an unknown kind or a missing prompt', function (): void {
    $seller = $this->seller();
    $listing = $this->listing($seller);

    $response = $this->actingAs($seller, 'seller')->post("/seller/listings/{$listing->id}/modifiers", [
        'kind' => 'nonsense',
        'prompt' => '',
        'position' => 0,
    ]);

    $response->assertSessionHasErrors(['kind', 'prompt']);
});

it('reads a signed, dollar-formatted extra charge the same way it renders one', function (): void {
    $seller = $this->seller();
    $listing = $this->listing($seller);

    $this->actingAs($seller, 'seller')->post("/seller/listings/{$listing->id}/modifiers", [
        'kind' => 'text',
        'prompt' => 'Name to letter',
        'position' => 0,
        'add_on_price' => '+$2.00',
    ]);

    $modifier = Modifier::where('listing_id', $listing->id)->sole();
    expect($modifier->add_on_price_cents)->toBe(200);
});

it('reads an em dash as no extra charge', function (): void {
    $seller = $this->seller();
    $listing = $this->listing($seller);

    $this->actingAs($seller, 'seller')->post("/seller/listings/{$listing->id}/modifiers", [
        'kind' => 'text',
        'prompt' => 'Name to letter',
        'position' => 0,
        'add_on_price' => '—',
    ]);

    $modifier = Modifier::where('listing_id', $listing->id)->sole();
    expect($modifier->add_on_price_cents)->toBe(0);
});

it('refuses a malformed extra charge or rate', function (): void {
    $seller = $this->seller();
    $listing = $this->listing($seller);

    $response = $this->actingAs($seller, 'seller')->post("/seller/listings/{$listing->id}/modifiers", [
        'kind' => 'measurement',
        'prompt' => 'Waist',
        'position' => 0,
        'add_on_price' => 'lots',
        'rate' => 'lots',
    ]);

    $response->assertSessionHasErrors(['add_on_price', 'rate']);
});
