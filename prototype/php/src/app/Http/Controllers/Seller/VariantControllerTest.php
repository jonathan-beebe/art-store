<?php

declare(strict_types=1);

namespace App\Http\Controllers\Seller;

use App\Actions\Configurator\CreateVariant;
use App\Domain\RateLimiting\RateLimitValue;
use App\Models\CartItem;
use App\Models\Fulfillment;
use App\Models\OptionAxis;
use App\Models\OptionValue;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Variant;
use App\Models\VariantOption;
use Illuminate\Support\Facades\Config;
use LogicException;

it('lists the listing’s sparse variants with a derived price', function (): void {
    $seller = $this->seller();
    $listing = $this->listing($seller, ['price_cents' => 2000]);
    $axis = OptionAxis::factory()->create(['listing_id' => $listing->id, 'name' => 'Size']);
    $large = OptionValue::factory()->create(['axis_id' => $axis->id, 'label' => 'Large', 'surcharge_cents' => 500]);
    $variant = Variant::factory()->create(['listing_id' => $listing->id, 'combo_key' => $large->id]);
    VariantOption::factory()->create(['variant_id' => $variant->id, 'axis_id' => $axis->id, 'option_value_id' => $large->id]);

    $response = $this->actingAs($seller, 'seller')->get("/seller/listings/{$listing->id}/variants");

    $response->assertOk();
    $response->assertSee('Large');
    $response->assertSee('$25.00', escape: false);
});

it('derives a standalone combination’s price from its own option, ignoring the listing base', function (): void {
    $seller = $this->seller();
    $listing = $this->listing($seller, ['price_cents' => 1800]);
    $size = OptionAxis::factory()->standalone()->create(['listing_id' => $listing->id, 'name' => 'Size']);
    $elevenByFourteen = OptionValue::factory()->priced(2400)->create(['axis_id' => $size->id, 'label' => '11x14']);
    $variant = Variant::factory()->create(['listing_id' => $listing->id, 'combo_key' => $elevenByFourteen->id]);
    VariantOption::factory()->create(['variant_id' => $variant->id, 'axis_id' => $size->id, 'option_value_id' => $elevenByFourteen->id]);

    $response = $this->actingAs($seller, 'seller')->get("/seller/listings/{$listing->id}/variants");

    $response->assertOk();
    $response->assertSee('11x14');
    $response->assertSee('$24.00', escape: false);
});

it('offers the add-variant form while a combination remains', function (): void {
    $seller = $this->seller();
    $listing = $this->listing($seller);
    $axis = OptionAxis::factory()->create(['listing_id' => $listing->id]);
    OptionValue::factory()->create(['axis_id' => $axis->id]);
    OptionValue::factory()->create(['axis_id' => $axis->id]);

    $response = $this->actingAs($seller, 'seller')->get("/seller/listings/{$listing->id}/variants");

    $response->assertOk();
    $response->assertSee('Add it');
    $response->assertDontSee('Every combination exists');
});

it('replaces the add-variant form with a note once every combination has a row', function (): void {
    $seller = $this->seller();
    $listing = $this->listing($seller);
    $axis = OptionAxis::factory()->create(['listing_id' => $listing->id]);
    $only = OptionValue::factory()->create(['axis_id' => $axis->id]);
    $variant = Variant::factory()->create(['listing_id' => $listing->id, 'combo_key' => $only->id]);
    VariantOption::factory()->create(['variant_id' => $variant->id, 'axis_id' => $axis->id, 'option_value_id' => $only->id]);

    $response = $this->actingAs($seller, 'seller')->get("/seller/listings/{$listing->id}/variants");

    $response->assertOk();
    $response->assertSee('Every combination exists — edit rows above.');
    $response->assertDontSee('Add it');
});

it('refuses another sellers listing variants page', function (): void {
    $listing = $this->listing($this->seller('Other Studio'));

    $response = $this->actingAs($this->seller(), 'seller')->get("/seller/listings/{$listing->id}/variants");

    $response->assertNotFound();
});

it('adds a sparse variant selecting one option per axis', function (): void {
    $seller = $this->seller();
    $listing = $this->listing($seller);
    $axis = OptionAxis::factory()->create(['listing_id' => $listing->id]);
    $value = OptionValue::factory()->create(['axis_id' => $axis->id]);

    $response = $this->actingAs($seller, 'seller')->post("/seller/listings/{$listing->id}/variants", [
        'option_value_id' => [$axis->id => $value->id],
        'sku' => 'SKU-1',
        'price_override' => '19.99',
        'quantity' => 3,
    ]);

    $response->assertRedirect(route('seller.listings.variants.index', $listing));
    $variant = Variant::where('listing_id', $listing->id)->sole();
    expect($variant->sku)->toBe('SKU-1')
        ->and($variant->price_override_cents)->toBe(1999)
        ->and($variant->quantity)->toBe(3)
        ->and($variant->enabled)->toBeTrue();
});

it('adds the legacy single variant for an axis-free listing', function (): void {
    $seller = $this->seller();
    $listing = $this->listing($seller);

    $this->actingAs($seller, 'seller')->post("/seller/listings/{$listing->id}/variants", ['quantity' => 1]);

    expect(Variant::where('listing_id', $listing->id)->sole()->combo_key)->toBe('');
});

it('refuses an option value from the wrong axis', function (): void {
    $seller = $this->seller();
    $listing = $this->listing($seller);
    $axis = OptionAxis::factory()->create(['listing_id' => $listing->id]);
    $otherAxis = OptionAxis::factory()->create(['listing_id' => $listing->id]);
    $wrongValue = OptionValue::factory()->create(['axis_id' => $otherAxis->id]);

    $response = $this->actingAs($seller, 'seller')->post("/seller/listings/{$listing->id}/variants", [
        'option_value_id' => [$axis->id => $wrongValue->id],
    ]);

    $response->assertSessionHasErrors("option_value_id.{$axis->id}");
    expect(Variant::count())->toBe(0);
});

it('updates a variant’s override, sku, quantity, serialization, and enablement', function (): void {
    $seller = $this->seller();
    $listing = $this->listing($seller);
    $variant = Variant::factory()->create(['listing_id' => $listing->id]);

    $response = $this->actingAs($seller, 'seller')->put("/seller/listings/{$listing->id}/variants/{$variant->id}", [
        'sku' => 'NEW-SKU',
        'price_override' => '9.00',
        'quantity' => 5,
        'enabled' => '1',
    ]);

    $response->assertRedirect(route('seller.listings.variants.index', $listing));
    $updated = $variant->fresh();
    expect($updated?->sku)->toBe('NEW-SKU')
        ->and($updated?->price_override_cents)->toBe(900)
        ->and($updated?->quantity)->toBe(5)
        ->and($updated?->enabled)->toBeTrue();
});

it('disables a variant when the enabled box is unchecked', function (): void {
    $seller = $this->seller();
    $listing = $this->listing($seller);
    $variant = Variant::factory()->create(['listing_id' => $listing->id, 'enabled' => true]);

    $this->actingAs($seller, 'seller')->put("/seller/listings/{$listing->id}/variants/{$variant->id}", ['quantity' => 1]);

    expect($variant->fresh()?->enabled)->toBeFalse();
});

it('answers not found updating a variant from another listing', function (): void {
    $seller = $this->seller();
    $listing = $this->listing($seller);
    $otherListing = $this->listing($seller);
    $variant = Variant::factory()->create(['listing_id' => $otherListing->id]);

    $response = $this->actingAs($seller, 'seller')->put("/seller/listings/{$listing->id}/variants/{$variant->id}", ['quantity' => 1]);

    $response->assertNotFound();
});

it('trips the listing-write limit adding a variant', function (): void {
    Config::set('rate_limits.listing_write', RateLimitValue::parse('1/1h', 'RATE_LIMIT_LISTING_WRITE'));
    $seller = $this->seller();
    $listing = $this->listing($seller);
    // An axis-free listing keeps a single variant, so this starts from none:
    // a pre-seeded one would refuse the priming create outright and leave
    // the count assertion proving the wrong rule.
    $this->actingAs($seller, 'seller')->post("/seller/listings/{$listing->id}/variants", ['quantity' => 1]);

    $response = $this->actingAs($seller, 'seller')->post("/seller/listings/{$listing->id}/variants", ['quantity' => 1, 'sku' => 'Second']);

    $response->assertStatus(429);
    expect(Variant::where('listing_id', $listing->id)->count())->toBe(1);
});

it('trips the listing-write limit', function (string $action): void {
    Config::set('rate_limits.listing_write', RateLimitValue::parse('1/1h', 'RATE_LIMIT_LISTING_WRITE'));
    $seller = $this->seller();
    $listing = $this->listing($seller);
    $variant = Variant::factory()->create(['listing_id' => $listing->id, 'sku' => 'Old']);
    $this->actingAs($seller, 'seller')->post("/seller/listings/{$listing->id}/variants", ['quantity' => 1]);

    $response = match ($action) {
        'updating' => $this->actingAs($seller, 'seller')->put("/seller/listings/{$listing->id}/variants/{$variant->id}", ['sku' => 'New', 'quantity' => 1]),
        'removing' => $this->actingAs($seller, 'seller')->delete("/seller/listings/{$listing->id}/variants/{$variant->id}"),
        default => throw new LogicException("Unknown action: {$action}"),
    };

    $response->assertStatus(429);
    match ($action) {
        'updating' => expect($variant->fresh()?->sku)->toBe('Old'),
        'removing' => expect(Variant::find($variant->id))->not->toBeNull(),
    };
})->with(['updating', 'removing']);

it('A3: offers the one combination added and refuses the sibling combination never added', function (): void {
    $seller = $this->seller();
    $listing = $this->listing($seller);
    $size = OptionAxis::factory()->create(['listing_id' => $listing->id, 'name' => 'Size']);
    $ten = OptionValue::factory()->create(['axis_id' => $size->id, 'label' => '10']);
    $eleven = OptionValue::factory()->create(['axis_id' => $size->id, 'label' => '11', 'is_default' => true]);
    $metal = OptionAxis::factory()->create(['listing_id' => $listing->id, 'name' => 'Metal']);
    $silver = OptionValue::factory()->create(['axis_id' => $metal->id, 'label' => 'Silver']);
    $gold = OptionValue::factory()->create(['axis_id' => $metal->id, 'label' => 'Gold', 'is_default' => true]);
    app(CreateVariant::class)($listing, [$eleven, $gold]);

    $response = $this->actingAs($seller, 'seller')->get("/seller/listings/{$listing->id}/variants");

    $response->assertOk();
    $response->assertSee('Gold');
    $response->assertSee('Silver');
    $response->assertSee('not offered');
});

it('A5: an unchecked combination greys its row and shows unavailable with a reason in the buyer panel', function (): void {
    $seller = $this->seller();
    $listing = $this->listing($seller);
    $axis = OptionAxis::factory()->create(['listing_id' => $listing->id, 'name' => 'Size']);
    $small = OptionValue::factory()->create(['axis_id' => $axis->id, 'label' => 'Small', 'is_default' => true]);
    $large = OptionValue::factory()->create(['axis_id' => $axis->id, 'label' => 'Large']);
    app(CreateVariant::class)($listing, [$small]);
    $offVariant = app(CreateVariant::class)($listing, [$large]);
    $offVariant->update(['enabled' => false]);

    $response = $this->actingAs($seller, 'seller')->get("/seller/listings/{$listing->id}/variants");

    $response->assertOk();
    $response->assertSee("you don't make this", escape: false);
    $response->assertSee('not offered');
});

it('A6: a combination sold out to zero greys only that option, leaving the other purchasable', function (): void {
    $seller = $this->seller();
    $listing = $this->listing($seller);
    $axis = OptionAxis::factory()->create(['listing_id' => $listing->id, 'name' => 'Color']);
    $red = OptionValue::factory()->create(['axis_id' => $axis->id, 'label' => 'Red', 'is_default' => true]);
    $blue = OptionValue::factory()->create(['axis_id' => $axis->id, 'label' => 'Blue']);
    app(CreateVariant::class)($listing, [$red], quantity: 5);
    app(CreateVariant::class)($listing, [$blue], quantity: 0);

    $response = $this->actingAs($seller, 'seller')->get("/seller/listings/{$listing->id}/variants");

    $response->assertOk();
    $response->assertSee('Red');
    $response->assertSee('Blue');
    $response->assertSee('out of stock');
});

it('A7: distinct stock counts per combination render and persist, with a low marker at three or fewer', function (): void {
    $seller = $this->seller();
    $listing = $this->listing($seller);
    $axis = OptionAxis::factory()->create(['listing_id' => $listing->id]);
    $plenty = OptionValue::factory()->create(['axis_id' => $axis->id, 'label' => 'Plenty']);
    $scarce = OptionValue::factory()->create(['axis_id' => $axis->id, 'label' => 'Scarce']);
    $plentyVariant = app(CreateVariant::class)($listing, [$plenty], quantity: 40);
    $scarceVariant = app(CreateVariant::class)($listing, [$scarce], quantity: 3);

    $response = $this->actingAs($seller, 'seller')->get("/seller/listings/{$listing->id}/variants");

    $response->assertOk();
    $response->assertSee('value="40"', false);
    $response->assertSee('value="3"', false);
    $response->assertSee('low');

    $this->actingAs($seller, 'seller')->put("/seller/listings/{$listing->id}/variants/{$scarceVariant->id}", ['quantity' => 12]);

    expect($scarceVariant->fresh()?->quantity)->toBe(12);
    expect($plentyVariant->fresh()?->quantity)->toBe(40);
});

it('A8: stopping offering every combination for one option value works end to end, and the price sweep renders as a coming slot', function (): void {
    $seller = $this->seller();
    $listing = $this->listing($seller);
    $axis = OptionAxis::factory()->create(['listing_id' => $listing->id, 'name' => 'Size']);
    $large = OptionValue::factory()->create(['axis_id' => $axis->id, 'label' => 'Large']);
    $variant = app(CreateVariant::class)($listing, [$large]);

    $response = $this->actingAs($seller, 'seller')->get("/seller/listings/{$listing->id}/variants");
    $response->assertOk();
    $response->assertSee('stop offering them');
    $response->assertSee('offer them');
    $response->assertSee('coming — not in this version');
    $response->assertSee('add $10.00 to the price');

    $this->actingAs($seller, 'seller')->post("/seller/listings/{$listing->id}/variants/bulk", [
        'option_value_id' => $large->id,
        'enabled' => '0',
    ]);

    expect($variant->fresh()?->enabled)->toBeFalse();
});

it('A11: setting a combination’s own code persists', function (): void {
    $seller = $this->seller();
    $listing = $this->listing($seller);
    $axis = OptionAxis::factory()->create(['listing_id' => $listing->id]);
    $value = OptionValue::factory()->create(['axis_id' => $axis->id]);
    $variant = app(CreateVariant::class)($listing, [$value]);

    $this->actingAs($seller, 'seller')->put("/seller/listings/{$listing->id}/variants/{$variant->id}", [
        'sku' => 'WORKSHOP-07',
        'quantity' => 1,
    ]);

    expect($variant->fresh()?->sku)->toBe('WORKSHOP-07');

    $response = $this->actingAs($seller, 'seller')->get("/seller/listings/{$listing->id}/variants");
    $response->assertSee('WORKSHOP-07');
});

it('C11: the how-stock-works card renders and a blank-stock combination stays available in the buyer panel', function (): void {
    $seller = $this->seller();
    $listing = $this->listing($seller);
    $axis = OptionAxis::factory()->create(['listing_id' => $listing->id, 'name' => 'Size']);
    $oneSize = OptionValue::factory()->create(['axis_id' => $axis->id, 'label' => 'One size', 'is_default' => true]);
    app(CreateVariant::class)($listing, [$oneSize], quantity: null);

    $response = $this->actingAs($seller, 'seller')->get("/seller/listings/{$listing->id}/variants");

    $response->assertOk();
    $response->assertSee('How stock works here');
    $response->assertSee('Made in batches');
    $response->assertSee('Made to order');
    $response->assertSee('One of a kind');
    $response->assertDontSee('out of stock');
    $response->assertDontSee('not offered');
});

it('offers to start listing pieces for a listing with no choices', function (): void {
    $seller = $this->seller();
    $listing = $this->listing($seller);

    $response = $this->actingAs($seller, 'seller')->get("/seller/listings/{$listing->id}/variants");

    $response->assertOk();
    $response->assertSee('Every piece one of a kind?');
    $response->assertSee('Start listing pieces');
});

it('starts listing pieces for a no-choices listing, landing on that combination’s pieces screen', function (): void {
    $seller = $this->seller();
    $listing = $this->listing($seller);

    $response = $this->actingAs($seller, 'seller')->post("/seller/listings/{$listing->id}/variants", ['is_serialized' => '1']);

    $variant = Variant::where('listing_id', $listing->id)->sole();
    expect($variant->is_serialized)->toBeTrue()
        ->and($variant->combo_key)->toBe('');
    $response->assertRedirect(route('seller.listings.variants.units.index', [$listing, $variant]));

    $again = $this->actingAs($seller, 'seller')->get("/seller/listings/{$listing->id}/variants");
    $again->assertSee('See your pieces');
    $again->assertDontSee('Start listing pieces');
});

it('removes a variant nothing depends on', function (): void {
    $seller = $this->seller();
    $listing = $this->listing($seller);
    $variant = Variant::factory()->create(['listing_id' => $listing->id]);

    $response = $this->actingAs($seller, 'seller')->delete("/seller/listings/{$listing->id}/variants/{$variant->id}");

    $response->assertRedirect(route('seller.listings.variants.index', $listing));
    expect(Variant::find($variant->id))->toBeNull();
});

it('refuses to remove a variant a cart still holds', function (): void {
    $seller = $this->seller();
    $listing = $this->listing($seller);
    $variant = Variant::factory()->create(['listing_id' => $listing->id]);
    CartItem::factory()->create(['listing_id' => $listing->id, 'variant_id' => $variant->id]);

    $response = $this->actingAs($seller, 'seller')->delete("/seller/listings/{$listing->id}/variants/{$variant->id}");

    $response->assertSessionHasErrors();
    expect(Variant::find($variant->id))->not->toBeNull();
});

it('refuses to remove a variant an order still awaiting shipment holds', function (): void {
    $seller = $this->seller();
    $listing = $this->listing($seller);
    $variant = Variant::factory()->create(['listing_id' => $listing->id]);
    $order = Order::factory()->paid()->create();
    Fulfillment::factory()->awaitingShipment()->create(['order_id' => $order->id, 'seller_id' => $seller->id]);
    OrderItem::factory()->create([
        'order_id' => $order->id,
        'listing_id' => $listing->id,
        'seller_id' => $seller->id,
        'variant_id' => $variant->id,
    ]);

    $response = $this->actingAs($seller, 'seller')->delete("/seller/listings/{$listing->id}/variants/{$variant->id}");

    $response->assertSessionHasErrors();
    expect(Variant::find($variant->id))->not->toBeNull();
});

it('removes a variant only a delivered order references', function (): void {
    $seller = $this->seller();
    $listing = $this->listing($seller);
    $variant = Variant::factory()->create(['listing_id' => $listing->id]);
    $order = Order::factory()->paid()->create();
    Fulfillment::factory()->delivered()->create(['order_id' => $order->id, 'seller_id' => $seller->id]);
    OrderItem::factory()->create([
        'order_id' => $order->id,
        'listing_id' => $listing->id,
        'seller_id' => $seller->id,
        'variant_id' => $variant->id,
    ]);

    $response = $this->actingAs($seller, 'seller')->delete("/seller/listings/{$listing->id}/variants/{$variant->id}");

    $response->assertRedirect(route('seller.listings.variants.index', $listing));
    expect(Variant::find($variant->id))->toBeNull();
});

it('refuses to remove another sellers variant', function (): void {
    $listing = $this->listing($this->seller('Other Studio'));
    $variant = Variant::factory()->create(['listing_id' => $listing->id]);

    $response = $this->actingAs($this->seller(), 'seller')->delete("/seller/listings/{$listing->id}/variants/{$variant->id}");

    $response->assertNotFound();
    expect(Variant::find($variant->id))->not->toBeNull();
});

it('BUG-008 unblocks removing the option value and axis a deleted variant no longer selects', function (): void {
    $seller = $this->seller();
    $listing = $this->listing($seller);
    $axis = OptionAxis::factory()->create(['listing_id' => $listing->id]);
    $value = OptionValue::factory()->create(['axis_id' => $axis->id]);
    $variant = Variant::factory()->create(['listing_id' => $listing->id]);
    VariantOption::factory()->create(['variant_id' => $variant->id, 'axis_id' => $axis->id, 'option_value_id' => $value->id]);

    $blockedValueRemoval = $this->actingAs($seller, 'seller')->delete("/seller/listings/{$listing->id}/option-axes/{$axis->id}/option-values/{$value->id}");
    $blockedValueRemoval->assertSessionHasErrors();

    $variantRemoval = $this->actingAs($seller, 'seller')->delete("/seller/listings/{$listing->id}/variants/{$variant->id}");
    $variantRemoval->assertSessionDoesntHaveErrors();
    expect(Variant::find($variant->id))->toBeNull();

    $valueRemoval = $this->actingAs($seller, 'seller')->delete("/seller/listings/{$listing->id}/option-axes/{$axis->id}/option-values/{$value->id}");
    $valueRemoval->assertSessionDoesntHaveErrors();
    expect(OptionValue::find($value->id))->toBeNull();

    $axisRemoval = $this->actingAs($seller, 'seller')->delete("/seller/listings/{$listing->id}/option-axes/{$axis->id}");
    $axisRemoval->assertSessionDoesntHaveErrors();
    expect(OptionAxis::find($axis->id))->toBeNull();
});
