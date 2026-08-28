<?php

declare(strict_types=1);

namespace App\Http\Controllers\Seller;

use App\Domain\Configurator\UnitState;
use App\Domain\RateLimiting\RateLimitValue;
use App\Models\Unit;
use App\Models\Variant;
use Illuminate\Support\Facades\Config;

it('lists a variant’s units and its derived available count', function (): void {
    $seller = $this->seller();
    $listing = $this->listing($seller);
    $variant = Variant::factory()->serialized()->create(['listing_id' => $listing->id]);
    Unit::factory()->create(['variant_id' => $variant->id, 'label' => '#1']);
    Unit::factory()->sold()->create(['variant_id' => $variant->id, 'label' => '#2']);

    $response = $this->actingAs($seller, 'seller')->get("/seller/listings/{$listing->id}/variants/{$variant->id}/units");

    $response->assertOk();
    $response->assertSee('#1');
    $response->assertSee('1'); // the derived available count
});

it('orders units naturally by label and shows humanized specs', function (): void {
    $seller = $this->seller();
    $listing = $this->listing($seller);
    $variant = Variant::factory()->serialized()->create(['listing_id' => $listing->id]);
    Unit::factory()->create(['variant_id' => $variant->id, 'label' => '#10']);
    Unit::factory()->create(['variant_id' => $variant->id, 'label' => '#2', 'specs_json' => ['height_mm' => 205]]);
    Unit::factory()->create(['variant_id' => $variant->id, 'label' => '#1']);

    $response = $this->actingAs($seller, 'seller')->get("/seller/listings/{$listing->id}/variants/{$variant->id}/units");

    $response->assertOk();
    $response->assertSeeInOrder(['#1', '#2', '#10']);
    $response->assertSee('Height: 205 mm');
});

it('refuses another sellers variant units page', function (): void {
    $listing = $this->listing($this->seller('Other Studio'));
    $variant = Variant::factory()->serialized()->create(['listing_id' => $listing->id]);

    $response = $this->actingAs($this->seller(), 'seller')->get("/seller/listings/{$listing->id}/variants/{$variant->id}/units");

    $response->assertNotFound();
});

it('adds a unit with its condition, measurements, and price override', function (): void {
    $seller = $this->seller();
    $listing = $this->listing($seller);
    $variant = Variant::factory()->serialized()->create(['listing_id' => $listing->id]);

    $response = $this->actingAs($seller, 'seller')->post("/seller/listings/{$listing->id}/variants/{$variant->id}/units", [
        'label' => '#12',
        'condition_note' => 'Small chip at base',
        'specs' => [['label' => 'Height', 'value' => '24 cm']],
        'price_override' => '45.00',
    ]);

    $response->assertRedirect(route('seller.listings.variants.units.index', [$listing, $variant]));
    $unit = Unit::where('variant_id', $variant->id)->sole();
    expect($unit->label)->toBe('#12')
        ->and($unit->condition_note)->toBe('Small chip at base')
        ->and($unit->specs_json)->toBe(['Height' => '24 cm'])
        ->and($unit->price_override_cents)->toBe(4500);
});

it('refuses a duplicate label on the same variant', function (): void {
    $seller = $this->seller();
    $listing = $this->listing($seller);
    $variant = Variant::factory()->serialized()->create(['listing_id' => $listing->id]);
    Unit::factory()->create(['variant_id' => $variant->id, 'label' => '#1']);

    $response = $this->actingAs($seller, 'seller')->post("/seller/listings/{$listing->id}/variants/{$variant->id}/units", [
        'label' => '#1',
    ]);

    $response->assertSessionHasErrors('label');
});

it('updates a unit’s label, state, measurements, and price override', function (): void {
    $seller = $this->seller();
    $listing = $this->listing($seller);
    $variant = Variant::factory()->serialized()->create(['listing_id' => $listing->id]);
    $unit = Unit::factory()->create(['variant_id' => $variant->id, 'label' => '#1']);

    $response = $this->actingAs($seller, 'seller')->put("/seller/listings/{$listing->id}/variants/{$variant->id}/units/{$unit->id}", [
        'label' => '#1',
        'state' => UnitState::Sold->value,
        'specs' => [['label' => 'Height', 'value' => '24 cm']],
        'price_override' => '50.00',
    ]);

    $response->assertRedirect(route('seller.listings.variants.units.index', [$listing, $variant]));
    $updated = $unit->fresh();
    expect($updated?->state)->toBe(UnitState::Sold)
        ->and($updated?->specs_json)->toBe(['Height' => '24 cm'])
        ->and($updated?->price_override_cents)->toBe(5000);
});

it('updates a unit with no specs field, clearing any it had', function (): void {
    $seller = $this->seller();
    $listing = $this->listing($seller);
    $variant = Variant::factory()->serialized()->create(['listing_id' => $listing->id]);
    $unit = Unit::factory()->create(['variant_id' => $variant->id, 'specs_json' => ['height_mm' => 10]]);

    $this->actingAs($seller, 'seller')->put("/seller/listings/{$listing->id}/variants/{$variant->id}/units/{$unit->id}", [
        'label' => $unit->label,
        'state' => UnitState::Available->value,
    ]);

    expect($unit->fresh()?->specs_json)->toBeNull();
});

it('answers not found updating a unit from another variant', function (): void {
    $seller = $this->seller();
    $listing = $this->listing($seller);
    $variant = Variant::factory()->serialized()->create(['listing_id' => $listing->id, 'combo_key' => 'a']);
    $otherVariant = Variant::factory()->serialized()->create(['listing_id' => $listing->id, 'combo_key' => 'b']);
    $unit = Unit::factory()->create(['variant_id' => $otherVariant->id]);

    $response = $this->actingAs($seller, 'seller')->put("/seller/listings/{$listing->id}/variants/{$variant->id}/units/{$unit->id}", [
        'label' => $unit->label,
        'state' => UnitState::Available->value,
    ]);

    $response->assertNotFound();
});

it('trips the listing-write limit adding a unit', function (): void {
    Config::set('rate_limits.listing_write', RateLimitValue::parse('1/1h', 'RATE_LIMIT_LISTING_WRITE'));
    $seller = $this->seller();
    $listing = $this->listing($seller);
    $variant = Variant::factory()->serialized()->create(['listing_id' => $listing->id]);
    $this->actingAs($seller, 'seller')->post("/seller/listings/{$listing->id}/variants/{$variant->id}/units", ['label' => '#1']);

    $response = $this->actingAs($seller, 'seller')->post("/seller/listings/{$listing->id}/variants/{$variant->id}/units", ['label' => '#2']);

    $response->assertStatus(429);
    expect(Unit::where('variant_id', $variant->id)->count())->toBe(1);
});

it('trips the listing-write limit updating a unit', function (): void {
    Config::set('rate_limits.listing_write', RateLimitValue::parse('1/1h', 'RATE_LIMIT_LISTING_WRITE'));
    $seller = $this->seller();
    $listing = $this->listing($seller);
    $variant = Variant::factory()->serialized()->create(['listing_id' => $listing->id]);
    $unit = Unit::factory()->create(['variant_id' => $variant->id, 'label' => '#1']);
    $this->actingAs($seller, 'seller')->post("/seller/listings/{$listing->id}/variants/{$variant->id}/units", ['label' => '#2']);

    $response = $this->actingAs($seller, 'seller')->put("/seller/listings/{$listing->id}/variants/{$variant->id}/units/{$unit->id}", [
        'label' => '#1', 'state' => UnitState::Sold->value,
    ]);

    $response->assertStatus(429);
    expect($unit->fresh()?->state)->toBe(UnitState::Available);
});

it('C1: renders each piece’s name, condition, measurements, and price, and lists them the same way in the buyer panel', function (): void {
    $seller = $this->seller();
    $listing = $this->listing($seller, ['price_cents' => 3000]);
    $variant = Variant::factory()->serialized()->create(['listing_id' => $listing->id]);
    Unit::factory()->create([
        'variant_id' => $variant->id,
        'label' => '#07',
        'condition_note' => 'Polished; small dent at base',
        'specs_json' => ['height_mm' => 240, 'weight_g' => 610],
        'price_override_cents' => 4800,
    ]);
    Unit::factory()->create([
        'variant_id' => $variant->id,
        'label' => '#12',
        'condition_note' => 'Original patina, unrestored',
        'specs_json' => ['height_mm' => 310],
        'price_override_cents' => null,
    ]);

    $response = $this->actingAs($seller, 'seller')->get("/seller/listings/{$listing->id}/variants/{$variant->id}/units");

    $response->assertOk();
    // the main list: name, condition, measurements, price
    $response->assertSee('#07');
    $response->assertSee('Polished; small dent at base');
    $response->assertSee('Height: 240 mm');
    $response->assertSee('Weight: 610 g');
    $response->assertSee('$48.00');
    // the piece with no override falls back to the combination's derived price
    $response->assertSee('#12');
    $response->assertSee('Original patina, unrestored');
    $response->assertSee('$30.00');
    // the buyer panel shows the same pieces, specs, and prices
    $response->assertSee('Choose your piece');
    $response->assertSeeInOrder(['Choose your piece', '#07', 'Height: 240 mm', '$48.00', '#12', 'Height: 310 mm', '$30.00']);
});

it('C2: a sold piece renders greyed with "sold", is absent from the buyer panel, and the count line reflects it', function (): void {
    $seller = $this->seller();
    $listing = $this->listing($seller);
    $variant = Variant::factory()->serialized()->create(['listing_id' => $listing->id]);
    Unit::factory()->create(['variant_id' => $variant->id, 'label' => '#01']);
    Unit::factory()->sold()->create(['variant_id' => $variant->id, 'label' => '#02', 'condition_note' => 'Light tarnish throughout']);

    $response = $this->actingAs($seller, 'seller')->get("/seller/listings/{$listing->id}/variants/{$variant->id}/units");

    $response->assertOk();
    $response->assertSee('2 pieces');
    $response->assertSee('1 available');
    $response->assertSee('1 sold');
    $response->assertSee('#02');
    $response->assertSee('sold');

    // the "Choose your piece" panel never lists the sold piece
    $content = $response->getContent();
    $panelStart = mb_strpos((string) $content, 'Choose your piece');
    expect($panelStart)->not->toBeFalse();
    expect(mb_strpos((string) $content, '#02', (int) $panelStart))->toBeFalse();
});

it('renders a reserved piece as "on hold" rather than the raw enum word', function (): void {
    $seller = $this->seller();
    $listing = $this->listing($seller);
    $variant = Variant::factory()->serialized()->create(['listing_id' => $listing->id]);
    Unit::factory()->reserved()->create(['variant_id' => $variant->id, 'label' => '#03']);

    $response = $this->actingAs($seller, 'seller')->get("/seller/listings/{$listing->id}/variants/{$variant->id}/units");

    $response->assertOk();
    $response->assertSee('on hold');
    $response->assertSee('1 on hold');
    $response->assertDontSee('Reserved');
    $response->assertDontSee('reserved');
});

it('adding a piece via labeled measurement rows persists and renders the formatted line, with no JSON visible', function (): void {
    $seller = $this->seller();
    $listing = $this->listing($seller);
    $variant = Variant::factory()->serialized()->create(['listing_id' => $listing->id]);

    $this->actingAs($seller, 'seller')->post("/seller/listings/{$listing->id}/variants/{$variant->id}/units", [
        'label' => '#53',
        'specs' => [
            ['label' => 'Height', 'value' => '26 cm'],
            ['label' => '', 'value' => ''],
            ['label' => '', 'value' => ''],
        ],
    ]);

    expect(Unit::where('variant_id', $variant->id)->sole()->specs_json)->toBe(['Height' => '26 cm']);

    $response = $this->actingAs($seller, 'seller')->get("/seller/listings/{$listing->id}/variants/{$variant->id}/units");

    $response->assertOk();
    $response->assertSee('Height: 26 cm');
    $response->assertDontSee('{"Height"');
    $response->assertDontSee('Specs (JSON)');
});

it('C4: shows the honest note that selling by weight or length is not supported yet', function (): void {
    $seller = $this->seller();
    $listing = $this->listing($seller);
    $variant = Variant::factory()->serialized()->create(['listing_id' => $listing->id]);

    $response = $this->actingAs($seller, 'seller')->get("/seller/listings/{$listing->id}/variants/{$variant->id}/units");

    $response->assertOk();
    $response->assertSee('Selling by weight or length');
    $response->assertSee("isn't supported yet; say so in the listing page for now.", false);
});

it('C1: shows the honest note that per-piece photos are not in this version', function (): void {
    $seller = $this->seller();
    $listing = $this->listing($seller);
    $variant = Variant::factory()->serialized()->create(['listing_id' => $listing->id]);

    $response = $this->actingAs($seller, 'seller')->get("/seller/listings/{$listing->id}/variants/{$variant->id}/units");

    $response->assertOk();
    $response->assertSee('Per-piece photos are');
    $response->assertSee('coming — not in this version', false);
});

it('never names the schema word "variant" on the pieces screen', function (): void {
    $seller = $this->seller();
    $listing = $this->listing($seller);
    $variant = Variant::factory()->serialized()->create(['listing_id' => $listing->id]);
    Unit::factory()->create(['variant_id' => $variant->id]);

    $response = $this->actingAs($seller, 'seller')->get("/seller/listings/{$listing->id}/variants/{$variant->id}/units");

    $response->assertOk();
    $visibleText = strip_tags((string) $response->getContent());
    expect(mb_stripos($visibleText, 'variant'))->toBeFalse();
});

it('shows an expanded edit form for the piece named in the edit query parameter', function (): void {
    $seller = $this->seller();
    $listing = $this->listing($seller);
    $variant = Variant::factory()->serialized()->create(['listing_id' => $listing->id]);
    $unit = Unit::factory()->create(['variant_id' => $variant->id, 'label' => '#09', 'condition_note' => 'Light tarnish']);

    $response = $this->actingAs($seller, 'seller')->get("/seller/listings/{$listing->id}/variants/{$variant->id}/units?edit={$unit->id}");

    $response->assertOk();
    $response->assertSee('Mark as');
    $response->assertSee('Cancel');
    $response->assertDontSee('State</label>', false);
});

it('IMPRV-015: the buyer panel preserves this screens own query params across a live refresh', function (): void {
    $seller = $this->seller();
    $listing = $this->listing($seller);
    $variant = Variant::factory()->serialized()->create(['listing_id' => $listing->id]);
    $unit = Unit::factory()->create(['variant_id' => $variant->id, 'label' => '#09']);

    $response = $this->actingAs($seller, 'seller')->get("/seller/listings/{$listing->id}/variants/{$variant->id}/units?edit={$unit->id}");

    $response->assertSee('<input type="hidden" name="edit" value="'.$unit->id.'">', escape: false);
});
