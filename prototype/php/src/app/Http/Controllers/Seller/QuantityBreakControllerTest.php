<?php

declare(strict_types=1);

namespace App\Http\Controllers\Seller;

use App\Domain\Configurator\ConfiguratorPublishValidation;
use App\Domain\RateLimiting\RateLimitValue;
use App\Models\QuantityBreak;
use Illuminate\Support\Facades\Config;

it('lists the listing’s quantity breaks', function (): void {
    $seller = $this->seller();
    $listing = $this->listing($seller);
    QuantityBreak::factory()->create(['listing_id' => $listing->id, 'min_qty' => 10, 'discount_bps' => 500]);

    $response = $this->actingAs($seller, 'seller')->get("/seller/listings/{$listing->id}/quantity-breaks");

    $response->assertOk();
    $response->assertSee('500');
});

it('refuses another sellers quantity breaks page', function (): void {
    $listing = $this->listing($this->seller('Other Studio'));

    $response = $this->actingAs($this->seller(), 'seller')->get("/seller/listings/{$listing->id}/quantity-breaks");

    $response->assertNotFound();
});

it('adds a quantity break tier', function (): void {
    $seller = $this->seller();
    $listing = $this->listing($seller);

    $response = $this->actingAs($seller, 'seller')->post("/seller/listings/{$listing->id}/quantity-breaks", [
        'min_qty' => 10,
        'discount_bps' => 1000,
    ]);

    $response->assertRedirect(route('seller.listings.quantity-breaks.index', $listing));
    $break = QuantityBreak::where('listing_id', $listing->id)->sole();
    expect($break->min_qty)->toBe(10)
        ->and($break->discount_bps)->toBe(1000);
});

it('refuses an eleventh tier', function (): void {
    $seller = $this->seller();
    $listing = $this->listing($seller);
    for ($i = 0; $i < ConfiguratorPublishValidation::MAX_QUANTITY_TIERS; $i++) {
        QuantityBreak::factory()->create(['listing_id' => $listing->id, 'min_qty' => 2 + $i, 'discount_bps' => 100]);
    }

    $response = $this->actingAs($seller, 'seller')->post("/seller/listings/{$listing->id}/quantity-breaks", [
        'min_qty' => 50,
        'discount_bps' => 100,
    ]);

    $response->assertSessionHasErrors('min_qty');
    expect(QuantityBreak::where('listing_id', $listing->id)->count())->toBe(ConfiguratorPublishValidation::MAX_QUANTITY_TIERS);
});

it('updates a quantity break tier past the cap, since it replaces an existing one', function (): void {
    $seller = $this->seller();
    $listing = $this->listing($seller);
    for ($i = 0; $i < ConfiguratorPublishValidation::MAX_QUANTITY_TIERS; $i++) {
        QuantityBreak::factory()->create(['listing_id' => $listing->id, 'min_qty' => 2 + $i, 'discount_bps' => 100]);
    }
    $break = QuantityBreak::where('listing_id', $listing->id)->firstOrFail();

    $response = $this->actingAs($seller, 'seller')->put("/seller/listings/{$listing->id}/quantity-breaks/{$break->id}", [
        'min_qty' => 500,
        'discount_bps' => 2000,
    ]);

    $response->assertRedirect(route('seller.listings.quantity-breaks.index', $listing));
    expect($break->fresh()?->min_qty)->toBe(500);
});

it('removes a quantity break tier', function (): void {
    $seller = $this->seller();
    $listing = $this->listing($seller);
    $break = QuantityBreak::factory()->create(['listing_id' => $listing->id]);

    $response = $this->actingAs($seller, 'seller')->delete("/seller/listings/{$listing->id}/quantity-breaks/{$break->id}");

    $response->assertRedirect(route('seller.listings.quantity-breaks.index', $listing));
    expect(QuantityBreak::find($break->id))->toBeNull();
});

it('refuses removing another sellers quantity break', function (): void {
    $listing = $this->listing($this->seller('Other Studio'));
    $break = QuantityBreak::factory()->create(['listing_id' => $listing->id]);

    $response = $this->actingAs($this->seller(), 'seller')->delete("/seller/listings/{$listing->id}/quantity-breaks/{$break->id}");

    $response->assertNotFound();
    expect(QuantityBreak::find($break->id))->not->toBeNull();
});

it('trips the listing-write limit adding a quantity break', function (): void {
    Config::set('rate_limits.listing_write', RateLimitValue::parse('1/1h', 'RATE_LIMIT_LISTING_WRITE'));
    $seller = $this->seller();
    $listing = $this->listing($seller);
    $this->actingAs($seller, 'seller')->post("/seller/listings/{$listing->id}/quantity-breaks", ['min_qty' => 2, 'discount_bps' => 100]);

    $response = $this->actingAs($seller, 'seller')->post("/seller/listings/{$listing->id}/quantity-breaks", ['min_qty' => 3, 'discount_bps' => 100]);

    $response->assertStatus(429);
    expect(QuantityBreak::where('listing_id', $listing->id)->count())->toBe(1);
});

it('trips the listing-write limit updating a quantity break', function (): void {
    Config::set('rate_limits.listing_write', RateLimitValue::parse('1/1h', 'RATE_LIMIT_LISTING_WRITE'));
    $seller = $this->seller();
    $listing = $this->listing($seller);
    $break = QuantityBreak::factory()->create(['listing_id' => $listing->id, 'min_qty' => 2]);
    $this->actingAs($seller, 'seller')->post("/seller/listings/{$listing->id}/quantity-breaks", ['min_qty' => 3, 'discount_bps' => 100]);

    $response = $this->actingAs($seller, 'seller')->put("/seller/listings/{$listing->id}/quantity-breaks/{$break->id}", ['min_qty' => 99, 'discount_bps' => 100]);

    $response->assertStatus(429);
    expect($break->fresh()?->min_qty)->toBe(2);
});

it('trips the listing-write limit removing a quantity break', function (): void {
    Config::set('rate_limits.listing_write', RateLimitValue::parse('1/1h', 'RATE_LIMIT_LISTING_WRITE'));
    $seller = $this->seller();
    $listing = $this->listing($seller);
    $break = QuantityBreak::factory()->create(['listing_id' => $listing->id]);
    $this->actingAs($seller, 'seller')->post("/seller/listings/{$listing->id}/quantity-breaks", ['min_qty' => 3, 'discount_bps' => 100]);

    $response = $this->actingAs($seller, 'seller')->delete("/seller/listings/{$listing->id}/quantity-breaks/{$break->id}");

    $response->assertStatus(429);
    expect(QuantityBreak::find($break->id))->not->toBeNull();
});
