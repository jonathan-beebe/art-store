<?php

declare(strict_types=1);

namespace App\Http\Controllers\Seller;

use App\Domain\Configurator\ModifierKind;
use App\Domain\RateLimiting\RateLimitValue;
use App\Models\Modifier;
use Illuminate\Support\Facades\Config;

it('lists the listing’s modifiers', function (): void {
    $seller = $this->seller();
    $listing = $this->listing($seller);
    Modifier::factory()->create(['listing_id' => $listing->id, 'prompt' => 'Personalization text']);

    $response = $this->actingAs($seller, 'seller')->get("/seller/listings/{$listing->id}/modifiers");

    $response->assertOk();
    $response->assertSee('Personalization text');
});

it('refuses another sellers modifiers page', function (): void {
    $listing = $this->listing($this->seller('Other Studio'));

    $response = $this->actingAs($this->seller(), 'seller')->get("/seller/listings/{$listing->id}/modifiers");

    $response->assertNotFound();
});

it('adds a text modifier', function (): void {
    $seller = $this->seller();
    $listing = $this->listing($seller);

    $response = $this->actingAs($seller, 'seller')->post("/seller/listings/{$listing->id}/modifiers", [
        'kind' => 'text',
        'prompt' => 'Personalization text',
        'position' => 0,
        'add_on_price' => '2.00',
        'char_limit' => 40,
    ]);

    $response->assertRedirect(route('seller.listings.modifiers.index', $listing));
    $modifier = Modifier::where('listing_id', $listing->id)->sole();
    expect($modifier->kind)->toBe(ModifierKind::Text)
        ->and($modifier->prompt)->toBe('Personalization text')
        ->and($modifier->add_on_price_cents)->toBe(200)
        ->and($modifier->char_limit)->toBe(40);
});

it('adds a measurement modifier', function (): void {
    $seller = $this->seller();
    $listing = $this->listing($seller);

    $this->actingAs($seller, 'seller')->post("/seller/listings/{$listing->id}/modifiers", [
        'kind' => 'measurement',
        'prompt' => 'Engraved length',
        'position' => 0,
        'unit' => 'mm',
        'min_value' => '10',
        'max_value' => '100',
        'rate' => '0.50',
    ]);

    $modifier = Modifier::where('listing_id', $listing->id)->sole();
    expect($modifier->kind)->toBe(ModifierKind::Measurement)
        ->and($modifier->unit)->toBe('mm')
        ->and($modifier->min_value)->toBe(10.0)
        ->and($modifier->max_value)->toBe(100.0)
        ->and($modifier->rate_cents_per_unit)->toBe(50);
});

it('updates a modifier', function (): void {
    $seller = $this->seller();
    $listing = $this->listing($seller);
    $modifier = Modifier::factory()->create(['listing_id' => $listing->id, 'prompt' => 'Old prompt']);

    $response = $this->actingAs($seller, 'seller')->put("/seller/listings/{$listing->id}/modifiers/{$modifier->id}", [
        'kind' => 'text',
        'prompt' => 'New prompt',
        'position' => 1,
        'required' => '1',
    ]);

    $response->assertRedirect(route('seller.listings.modifiers.index', $listing));
    $updated = $modifier->fresh();
    expect($updated?->prompt)->toBe('New prompt')
        ->and($updated?->required)->toBeTrue();
});

it('removes a modifier', function (): void {
    $seller = $this->seller();
    $listing = $this->listing($seller);
    $modifier = Modifier::factory()->create(['listing_id' => $listing->id]);

    $response = $this->actingAs($seller, 'seller')->delete("/seller/listings/{$listing->id}/modifiers/{$modifier->id}");

    $response->assertRedirect(route('seller.listings.modifiers.index', $listing));
    expect(Modifier::find($modifier->id))->toBeNull();
});

it('refuses removing another sellers modifier', function (): void {
    $listing = $this->listing($this->seller('Other Studio'));
    $modifier = Modifier::factory()->create(['listing_id' => $listing->id]);

    $response = $this->actingAs($this->seller(), 'seller')->delete("/seller/listings/{$listing->id}/modifiers/{$modifier->id}");

    $response->assertNotFound();
    expect(Modifier::find($modifier->id))->not->toBeNull();
});

it('trips the listing-write limit adding a modifier', function (): void {
    Config::set('rate_limits.listing_write', RateLimitValue::parse('1/1h', 'RATE_LIMIT_LISTING_WRITE'));
    $seller = $this->seller();
    $listing = $this->listing($seller);
    $this->actingAs($seller, 'seller')->post("/seller/listings/{$listing->id}/modifiers", ['kind' => 'text', 'prompt' => 'First', 'position' => 0]);

    $response = $this->actingAs($seller, 'seller')->post("/seller/listings/{$listing->id}/modifiers", ['kind' => 'text', 'prompt' => 'Second', 'position' => 1]);

    $response->assertStatus(429);
    expect(Modifier::where('listing_id', $listing->id)->count())->toBe(1);
});

it('trips the listing-write limit updating a modifier', function (): void {
    Config::set('rate_limits.listing_write', RateLimitValue::parse('1/1h', 'RATE_LIMIT_LISTING_WRITE'));
    $seller = $this->seller();
    $listing = $this->listing($seller);
    $modifier = Modifier::factory()->create(['listing_id' => $listing->id, 'prompt' => 'Old']);
    $this->actingAs($seller, 'seller')->post("/seller/listings/{$listing->id}/modifiers", ['kind' => 'text', 'prompt' => 'Consumes budget', 'position' => 0]);

    $response = $this->actingAs($seller, 'seller')->put("/seller/listings/{$listing->id}/modifiers/{$modifier->id}", ['kind' => 'text', 'prompt' => 'New', 'position' => 0]);

    $response->assertStatus(429);
    expect($modifier->fresh()?->prompt)->toBe('Old');
});

it('trips the listing-write limit removing a modifier', function (): void {
    Config::set('rate_limits.listing_write', RateLimitValue::parse('1/1h', 'RATE_LIMIT_LISTING_WRITE'));
    $seller = $this->seller();
    $listing = $this->listing($seller);
    $modifier = Modifier::factory()->create(['listing_id' => $listing->id]);
    $this->actingAs($seller, 'seller')->post("/seller/listings/{$listing->id}/modifiers", ['kind' => 'text', 'prompt' => 'Consumes budget', 'position' => 0]);

    $response = $this->actingAs($seller, 'seller')->delete("/seller/listings/{$listing->id}/modifiers/{$modifier->id}");

    $response->assertStatus(429);
    expect(Modifier::find($modifier->id))->not->toBeNull();
});
