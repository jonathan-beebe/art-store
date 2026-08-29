<?php

declare(strict_types=1);

namespace App\Http\Controllers\Seller;

use App\Domain\Configurator\ConfiguratorPublishValidation;
use App\Domain\RateLimiting\RateLimitValue;
use App\Models\QuantityBreak;
use Illuminate\Support\Facades\Config;

it('lists the listing’s quantity discounts as sentences with a per-item chip', function (): void {
    $seller = $this->seller();
    $listing = $this->listing($seller, ['price_cents' => 450]);
    QuantityBreak::factory()->create(['listing_id' => $listing->id, 'min_qty' => 50, 'discount_bps' => 1000]);

    $response = $this->actingAs($seller, 'seller')->get("/seller/listings/{$listing->id}/quantity-breaks");

    $response->assertOk();
    $response->assertSee('Quantity discounts');
    $response->assertSee('From');
    $response->assertSee('50');
    $response->assertSee('10');
    $response->assertSee('% off each');
    $response->assertSee('$4.05 per item', escape: false);
});

it('C10: shows the honest note about private pricing', function (): void {
    $seller = $this->seller();
    $listing = $this->listing($seller);

    $response = $this->actingAs($seller, 'seller')->get("/seller/listings/{$listing->id}/quantity-breaks");

    $response->assertSee(
        "A private price for one customer isn't available yet — quote bespoke jobs in Messages rather than publishing them as options anyone can buy.",
        escape: false,
    );
});

it('C3: shows the buyer panel with the tier table and a discounted breakdown', function (): void {
    $seller = $this->seller();
    $listing = $this->listing($seller, ['price_cents' => 450]);
    QuantityBreak::factory()->create(['listing_id' => $listing->id, 'min_qty' => 50, 'discount_bps' => 1000]);
    QuantityBreak::factory()->create(['listing_id' => $listing->id, 'min_qty' => 200, 'discount_bps' => 2200]);

    $response = $this->actingAs($seller, 'seller')->get("/seller/listings/{$listing->id}/quantity-breaks");

    // IMPRV-015: the tier table is the shared configurator partial's own
    // <table> now (identical to the shop page), not a panel-only sentence.
    $response->assertSee('What buyers see');
    $response->assertSee('200+');
    $response->assertSee('22% off');
    $response->assertSee('$702.00');
});

it('refuses another sellers quantity discounts page', function (): void {
    $listing = $this->listing($this->seller('Other Studio'));

    $response = $this->actingAs($this->seller(), 'seller')->get("/seller/listings/{$listing->id}/quantity-breaks");

    $response->assertNotFound();
});

it('adds a quantity break tier from a typed percent', function (): void {
    $seller = $this->seller();
    $listing = $this->listing($seller);

    $response = $this->actingAs($seller, 'seller')->post("/seller/listings/{$listing->id}/quantity-breaks", [
        'min_qty' => 10,
        'discount_percent' => '10',
    ]);

    $response->assertRedirect(route('seller.listings.quantity-breaks.index', $listing));
    $response->assertSessionHas('status', 'Breakpoint added.');
    $break = QuantityBreak::where('listing_id', $listing->id)->sole();
    expect($break->min_qty)->toBe(10)
        ->and($break->discount_bps)->toBe(1000);
});

it('converts a decimal percent to basis points and prefills it back as a percent', function (): void {
    $seller = $this->seller();
    $listing = $this->listing($seller);

    $this->actingAs($seller, 'seller')->post("/seller/listings/{$listing->id}/quantity-breaks", [
        'min_qty' => 12,
        'discount_percent' => '12.5',
    ]);

    $break = QuantityBreak::where('listing_id', $listing->id)->sole();
    expect($break->discount_bps)->toBe(1250);

    $response = $this->actingAs($seller, 'seller')->get("/seller/listings/{$listing->id}/quantity-breaks");
    $response->assertSee('value="12.5"', escape: false);
});

it('refuses a discount above 99.99 percent or at zero', function (): void {
    $seller = $this->seller();
    $listing = $this->listing($seller);

    $response = $this->actingAs($seller, 'seller')->post("/seller/listings/{$listing->id}/quantity-breaks", [
        'min_qty' => 10,
        'discount_percent' => '100',
    ]);
    $response->assertSessionHasErrors('discount_percent');

    $response = $this->actingAs($seller, 'seller')->post("/seller/listings/{$listing->id}/quantity-breaks", [
        'min_qty' => 10,
        'discount_percent' => '0',
    ]);
    $response->assertSessionHasErrors('discount_percent');
});

it('refuses an eleventh tier', function (): void {
    $seller = $this->seller();
    $listing = $this->listing($seller);
    for ($i = 0; $i < ConfiguratorPublishValidation::MAX_QUANTITY_TIERS; $i++) {
        QuantityBreak::factory()->create(['listing_id' => $listing->id, 'min_qty' => 2 + $i, 'discount_bps' => 100]);
    }

    $response = $this->actingAs($seller, 'seller')->post("/seller/listings/{$listing->id}/quantity-breaks", [
        'min_qty' => 50,
        'discount_percent' => '1',
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
        'discount_percent' => '20',
    ]);

    $response->assertRedirect(route('seller.listings.quantity-breaks.index', $listing));
    $response->assertSessionHas('status', 'Breakpoint updated.');
    expect($break->fresh()?->min_qty)->toBe(500)
        ->and($break->fresh()?->discount_bps)->toBe(2000);
});

it('removes a quantity break tier', function (): void {
    $seller = $this->seller();
    $listing = $this->listing($seller);
    $break = QuantityBreak::factory()->create(['listing_id' => $listing->id]);

    $response = $this->actingAs($seller, 'seller')->delete("/seller/listings/{$listing->id}/quantity-breaks/{$break->id}");

    $response->assertRedirect(route('seller.listings.quantity-breaks.index', $listing));
    $response->assertSessionHas('status', 'Breakpoint removed.');
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
    $this->actingAs($seller, 'seller')->post("/seller/listings/{$listing->id}/quantity-breaks", ['min_qty' => 2, 'discount_percent' => '1']);

    $response = $this->actingAs($seller, 'seller')->post("/seller/listings/{$listing->id}/quantity-breaks", ['min_qty' => 3, 'discount_percent' => '1']);

    $response->assertStatus(429);
    expect(QuantityBreak::where('listing_id', $listing->id)->count())->toBe(1);
});

it('trips the listing-write limit updating a quantity break', function (): void {
    Config::set('rate_limits.listing_write', RateLimitValue::parse('1/1h', 'RATE_LIMIT_LISTING_WRITE'));
    $seller = $this->seller();
    $listing = $this->listing($seller);
    $break = QuantityBreak::factory()->create(['listing_id' => $listing->id, 'min_qty' => 2]);
    $this->actingAs($seller, 'seller')->post("/seller/listings/{$listing->id}/quantity-breaks", ['min_qty' => 3, 'discount_percent' => '1']);

    $response = $this->actingAs($seller, 'seller')->put("/seller/listings/{$listing->id}/quantity-breaks/{$break->id}", ['min_qty' => 99, 'discount_percent' => '1']);

    $response->assertStatus(429);
    expect($break->fresh()?->min_qty)->toBe(2);
});

it('trips the listing-write limit removing a quantity break', function (): void {
    Config::set('rate_limits.listing_write', RateLimitValue::parse('1/1h', 'RATE_LIMIT_LISTING_WRITE'));
    $seller = $this->seller();
    $listing = $this->listing($seller);
    $break = QuantityBreak::factory()->create(['listing_id' => $listing->id]);
    $this->actingAs($seller, 'seller')->post("/seller/listings/{$listing->id}/quantity-breaks", ['min_qty' => 3, 'discount_percent' => '1']);

    $response = $this->actingAs($seller, 'seller')->delete("/seller/listings/{$listing->id}/quantity-breaks/{$break->id}");

    $response->assertStatus(429);
    expect(QuantityBreak::find($break->id))->not->toBeNull();
});
