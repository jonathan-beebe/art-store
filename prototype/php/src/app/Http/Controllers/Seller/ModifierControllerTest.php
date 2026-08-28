<?php

declare(strict_types=1);

namespace App\Http\Controllers\Seller;

use App\Domain\Configurator\ModifierKind;
use App\Domain\RateLimiting\RateLimitValue;
use App\Models\Modifier;
use App\Models\ModifierOption;
use App\Models\OptionAxis;
use App\Models\OptionValue;
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

it('names the screen and gives an honest intro and a back link', function (): void {
    $seller = $this->seller();
    $listing = $this->listing($seller, ['title' => 'Hand-Lettered Name Mug']);

    $response = $this->actingAs($seller, 'seller')->get("/seller/listings/{$listing->id}/modifiers");

    $response->assertOk();
    $response->assertSee('Questions you ask the buyer');
    $response->assertSee("you'll see them where you fulfill, never buried in a message thread", false);
    $response->assertSee('Hand-Lettered Name Mug');
    $response->assertSee(route('seller.listings.edit', $listing), false);
});

it('B1: shows the letter limit and offers it to the buyer as a maxlength', function (): void {
    $seller = $this->seller();
    $listing = $this->listing($seller);
    Modifier::factory()->create(['listing_id' => $listing->id, 'prompt' => 'What name should we letter?', 'char_limit' => 20]);

    $response = $this->actingAs($seller, 'seller')->get("/seller/listings/{$listing->id}/modifiers");

    $response->assertOk();
    $response->assertSee('buyers see the limit before they type');
    // IMPRV-015: the buyer panel's maxlength now comes from the same
    // partial the shop page renders, which carries no "Up to N letters"
    // hint — that hint was panel-only markup the real buyer page never
    // showed, dropped along with the rest of the hand-duplicated rendering.
    $response->assertSee('maxlength="20"', false);
});

it('B2: shows an extra charge on the question card and the buyer panel label', function (): void {
    $seller = $this->seller();
    $listing = $this->listing($seller);
    Modifier::factory()->create(['listing_id' => $listing->id, 'prompt' => 'Name to letter', 'add_on_price_cents' => 200]);

    $response = $this->actingAs($seller, 'seller')->get("/seller/listings/{$listing->id}/modifiers");

    $response->assertOk();
    $response->assertSeeInOrder(['Name to letter', '+$2.00']);
});

it('B3+B4: shows per-option prices on the rows and only the listed options to the buyer', function (): void {
    $seller = $this->seller();
    $listing = $this->listing($seller);
    $modifier = Modifier::factory()->select()->create(['listing_id' => $listing->id, 'prompt' => 'Lettering color']);
    ModifierOption::factory()->create(['modifier_id' => $modifier->id, 'label' => 'Gold leaf', 'add_on_price_cents' => 150]);
    ModifierOption::factory()->create(['modifier_id' => $modifier->id, 'label' => 'Black', 'add_on_price_cents' => 0]);

    $response = $this->actingAs($seller, 'seller')->get("/seller/listings/{$listing->id}/modifiers");

    $response->assertOk();
    $response->assertSee('+$1.50');
    $response->assertSee('Gold leaf (+$1.50)');
    $response->assertDontSee('Silver');
});

it('B5: renders the required checkbox and marks the buyer panel required', function (): void {
    $seller = $this->seller();
    $listing = $this->listing($seller);
    Modifier::factory()->required()->create(['listing_id' => $listing->id, 'prompt' => 'Name to letter']);

    $response = $this->actingAs($seller, 'seller')->get("/seller/listings/{$listing->id}/modifiers");

    $response->assertOk();
    $response->assertSee('Buyers must answer before they can buy');
    $response->assertSee('<span aria-hidden="true">*</span>', false);
});

it('B6: shows a scoped question on the applies panel and not on the other, with both captions', function (): void {
    $seller = $this->seller();
    $listing = $this->listing($seller);
    $axis = OptionAxis::factory()->create(['listing_id' => $listing->id, 'name' => 'Version']);
    $lettered = OptionValue::factory()->create(['axis_id' => $axis->id, 'label' => 'Hand-lettered']);
    OptionValue::factory()->create(['axis_id' => $axis->id, 'label' => 'Blank']);
    $modifier = Modifier::factory()->create(['listing_id' => $listing->id, 'prompt' => 'What name should we letter?']);
    $modifier->scopes()->create(['option_value_id' => $lettered->id]);

    $response = $this->actingAs($seller, 'seller')->get("/seller/listings/{$listing->id}/modifiers");

    $response->assertOk();
    $response->assertSee('Version: Hand-lettered');
    $response->assertSee('Version: Blank');
    $response->assertSee('Buyers who pick Blank never see this question');
    $response->assertSee("it simply isn't there", false);
});

it('IMPRV-015: the scoped-preview pair stays disabled rather than falsely interactive', function (): void {
    // ScopedListingPreview pins each panel to a specific option value from
    // stored scope data, never the request — a live form here would accept
    // a seller's clicks and then silently discard them on the next render,
    // so this pair renders with no <form> at all rather than one that lies.
    $seller = $this->seller();
    $listing = $this->listing($seller);
    $axis = OptionAxis::factory()->create(['listing_id' => $listing->id, 'name' => 'Version']);
    $lettered = OptionValue::factory()->create(['axis_id' => $axis->id, 'label' => 'Hand-lettered']);
    OptionValue::factory()->create(['axis_id' => $axis->id, 'label' => 'Blank']);
    $modifier = Modifier::factory()->create(['listing_id' => $listing->id, 'prompt' => 'What name should we letter?']);
    $modifier->scopes()->create(['option_value_id' => $lettered->id]);

    $response = $this->actingAs($seller, 'seller')->get("/seller/listings/{$listing->id}/modifiers");

    $response->assertDontSee('<form method="GET"', false);
    $response->assertSee('aria-disabled="true"', false);
});

it('IMPRV-015: the default buyer panel (no scoped question yet) is a live, enabled form', function (): void {
    $seller = $this->seller();
    $listing = $this->listing($seller);
    $axis = OptionAxis::factory()->create(['listing_id' => $listing->id, 'name' => 'Version']);
    OptionValue::factory()->create(['axis_id' => $axis->id, 'label' => 'Blank', 'is_default' => true]);

    $response = $this->actingAs($seller, 'seller')->get("/seller/listings/{$listing->id}/modifiers");

    $response->assertSee('<form method="GET" action="'.route('seller.listings.modifiers.index', $listing).'"', escape: false);
    $response->assertDontSee('<select disabled', false);
});

it('B7: renders limit fields for a measurement question and their attributes on the buyer input', function (): void {
    $seller = $this->seller();
    $listing = $this->listing($seller);
    Modifier::factory()->measurement('mm', 10.0, 100.0, 50)->create(['listing_id' => $listing->id, 'prompt' => 'Engraved length']);

    $response = $this->actingAs($seller, 'seller')->get("/seller/listings/{$listing->id}/modifiers");

    $response->assertOk();
    $response->assertSee('within limits you set');
    $response->assertSee('min="10"', false);
    $response->assertSee('max="100"', false);
    $response->assertSee('mm');
});

it('B8+B10+E3: renders the honest not-yet, coming, and footer slots with no live controls', function (): void {
    $seller = $this->seller();
    $listing = $this->listing($seller);
    $modifier = Modifier::factory()->select()->create(['listing_id' => $listing->id, 'prompt' => 'Lettering color']);
    ModifierOption::factory()->create(['modifier_id' => $modifier->id]);

    $response = $this->actingAs($seller, 'seller')->get("/seller/listings/{$listing->id}/modifiers");

    $response->assertOk();
    $response->assertSee('They attach a photo');
    $response->assertSee('not yet');
    $response->assertSee('Until this ships, ask buyers to send reference photos through Messages after ordering.');
    $response->assertSee('coming');
    $response->assertSee('not in this version');
    $response->assertSee('Gift wrap or rush turnaround?');
    $response->assertSee("add-on checkboxes on this listing aren't available yet.", false);
});

it('shows only the fields for the chosen type when adding a question', function (): void {
    $seller = $this->seller();
    $listing = $this->listing($seller);

    $response = $this->actingAs($seller, 'seller')->get("/seller/listings/{$listing->id}/modifiers?kind=measurement");

    $response->assertOk();
    $response->assertSee('$ per unit');
    $response->assertSee('Smallest allowed');
    $response->assertDontSee('Longest answer');
});

it('never renders the schema vocabulary the buyer-facing screen retired', function (): void {
    $seller = $this->seller();
    $listing = $this->listing($seller);
    Modifier::factory()->create(['listing_id' => $listing->id]);

    $response = $this->actingAs($seller, 'seller')->get("/seller/listings/{$listing->id}/modifiers");

    $response->assertOk();
    $response->assertDontSee('Modifiers');
    $response->assertDontSee('Add a modifier');
    $response->assertDontSee('Prompt');
    $response->assertDontSee('Char limit');
    $response->assertDontSee('Show this question only when');
});
