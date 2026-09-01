<?php

declare(strict_types=1);

namespace Tests;

use App\Domain\Listings\ListingStatus;
use App\Domain\Orders\FulfillmentStatus;
use App\Domain\Orders\OrderStatus;
use App\Models\CartItem;
use App\Models\Fulfillment;
use App\Models\Listing;
use App\Models\Modifier;
use App\Models\ModifierOption;
use App\Models\OptionAxis;
use App\Models\OptionValue;
use App\Models\Order;
use App\Models\Seller;
use App\Models\Variant;
use Database\Seeders\ConfiguratorArchetypeSeeder;
use Database\Seeders\TaxonomySeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/*
|--------------------------------------------------------------------------
| Configurator smoke tests
|--------------------------------------------------------------------------
|
| Two walks of the item configurator over HTTP, each carrying a listing from
| its seller-side setup through a buyer's purchase, with the priced
| breakdown frozen on the order it lands on.
|
*/

/**
 * The FEAT-028 walk: one archetype from FEAT-025's seeds carries a
 * configuration and a modifier answer through browse, configure, cart,
 * checkout, and payment, and the itemized breakdown that lands on the
 * receipt is asserted unchanged on every one of the three surfaces that
 * render it back.
 */
it('carries a configured ring purchase through checkout, freezing its breakdown for customer, seller, and admin', function (): void {
    $this->seed(TaxonomySeeder::class);
    $this->seed(ConfiguratorArchetypeSeeder::class);

    $listing = Listing::where('title', 'Engraved House Signet Ring')->sole();
    $seller = Seller::where('email', ConfiguratorArchetypeSeeder::EMAIL)->sole();
    $metal = OptionAxis::where('listing_id', $listing->id)->where('name', 'Metal')->sole();
    $roseGold = OptionValue::where('axis_id', $metal->id)->where('label', 'Rose Gold')->sole();
    $engraving = OptionAxis::where('listing_id', $listing->id)->where('name', 'Engraving')->sole();
    $outside = OptionValue::where('axis_id', $engraving->id)->where('label', 'Outside Only')->sole();
    $font = Modifier::where('listing_id', $listing->id)->where('prompt', 'Engraving Font')->sole();
    $block = ModifierOption::where('modifier_id', $font->id)->where('label', 'Block')->sole();
    $text = Modifier::where('listing_id', $listing->id)->where('prompt', 'Engraving Text')->sole();

    $customer = $this->arriveAs($this->verifiedCustomer());

    // Browse, then configure: the axis and modifier choices are GET params,
    // so the page opens on a concrete price before any script runs.
    $this->get("/art/{$listing->slug}")->assertOk()->assertSee('Engraved House Signet Ring');

    $this->get("/art/{$listing->slug}?".http_build_query([
        'axis' => [$metal->id => $roseGold->id, $engraving->id => $outside->id],
    ]))->assertOk()->assertSee('Rose Gold');

    $addedToCart = $this->post("/cart/{$listing->slug}", [
        'axis' => [$metal->id => $roseGold->id, $engraving->id => $outside->id],
        'modifier' => [$font->id => $block->id, $text->id => 'ADA'],
    ]);
    $addedToCart->assertRedirect(route('shop.cart'));

    $cartItem = CartItem::sole();
    $frozenTotal = $cartItem->currentBreakdown()->total()->format();

    $this->get('/cart')->assertOk()->assertSee('Rose Gold')->assertSee('ADA')->assertSee($frozenTotal);

    $placed = $this->post('/checkout', [
        'email' => $customer->email,
        'shipping_name' => 'Casey Whitfield',
        'shipping_line1' => '18 Harbour Road',
        'shipping_city' => 'Bristol',
        'shipping_region' => 'Bristol',
        'shipping_postal_code' => 'BS1 5TY',
        'shipping_country' => 'GB',
        'card_number' => '4242 4242 4242 4242',
    ]);

    $order = Order::sole();
    $placed->assertRedirect(route('shop.order', $order));
    expect($order->status)->toBe(OrderStatus::Paid);

    $item = $order->items()->sole();
    expect($item->hasVariant())->toBeTrue()
        ->and($item->lineTotal()->format())->toBe($frozenTotal);

    $this->get(route('shop.order', $order))
        ->assertOk()
        ->assertSee('Metal:')->assertSee('Rose Gold')
        ->assertSee('Engraving:')->assertSee('Outside Only')
        ->assertSee('Engraving Font:')->assertSee('Block')
        ->assertSee('Engraving Text:')->assertSee('ADA')
        ->assertSee($frozenTotal);

    $fulfillment = Fulfillment::sole();

    $this->actingAs($seller, 'seller')
        ->get(route('seller.orders.show', $fulfillment))
        ->assertOk()
        ->assertSee('Metal:')->assertSee('Rose Gold')
        ->assertSee($frozenTotal);

    $this->post("/seller/orders/{$fulfillment->id}/shipment", [
        'carrier' => 'Royal Mail',
        'tracking_number' => 'RM123456789GB',
    ])->assertRedirect(route('seller.orders.show', $fulfillment->id));

    expect($fulfillment->refresh()->status)->toBe(FulfillmentStatus::Shipped);

    $this->actingAs($this->admin(), 'admin')
        ->get(route('admin.orders.show', $order))
        ->assertOk()
        ->assertSee('Metal:')->assertSee('Rose Gold')
        ->assertSee($frozenTotal);

    // A later edit to the axis surcharge the customer bought at leaves the
    // placed order's frozen price and configuration exactly as they were.
    $roseGold->update(['surcharge_cents' => 5000]);

    expect($order->items()->sole()->lineTotal()->format())->toBe($frozenTotal);
});

/**
 * The DSGN-002 walk: a seller builds the Sunset Ridge shape entirely through
 * the row-based hub and its detail screens — no flat listing form — mixing
 * both pricing patterns on one listing (a standalone Size choice, an add-on
 * Frame choice), and a buyer's purchase of a non-default combination freezes
 * the same absolute-and-signed breakdown on the order it lands on.
 */
it('DSGN-002 builds Sunset Ridge through the row hub, then freezes a standalone-and-add-on purchase on its order', function (): void {
    Storage::fake('public');
    $seller = $this->seller();

    // Create: a listing is always unconfigured at birth, so DSGN-003's
    // "one thing, one price" on-ramp still asks for price and quantity up
    // front — a poster that later grows Sunset Ridge's standalone-and-add-on
    // hybrid entirely on the hub, the on-ramps-route-never-constrain rule
    // DSGN-003 is built on.
    $created = $this->actingAs($seller, 'seller')->post('/seller/listings', [
        'shape' => 'one',
        'title' => 'Sunset Ridge',
        'price' => '18.00',
        'quantity' => 5,
    ]);
    $listing = Listing::where('seller_id', $seller->id)->sole();
    $created->assertRedirect(route('seller.listings.edit', $listing));

    // Basics: the hub's "Your item" row edits here, on its own screen.
    $this->actingAs($seller, 'seller')->put("/seller/listings/{$listing->id}", [
        'title' => 'Sunset Ridge',
        'description' => 'A ridge line catching the last light of the day, printed on archival matte paper.',
        'dimensions' => '8 x 10 in',
        'price' => '18.00',
        'quantity' => 5,
    ])->assertRedirect(route('seller.listings.basics.edit', $listing));

    // Images: plural, its own screen — the create form's single upload is
    // no longer the only way a listing gets a photo.
    $this->actingAs($seller, 'seller')->post("/seller/listings/{$listing->id}/images", [
        'image' => UploadedFile::fake()->image('sunset-ridge.jpg'),
    ])->assertRedirect(route('seller.listings.images.index', $listing));
    expect($listing->images()->count())->toBe(1);

    // Choices: Size prices standalone (each option its own absolute price),
    // Frame adds on (a signed delta over whatever Size resolved to).
    $this->actingAs($seller, 'seller')->post("/seller/listings/{$listing->id}/option-axes", [
        'name' => 'Size', 'position' => 0, 'pricing_mode' => 'standalone',
    ])->assertRedirect(route('seller.listings.option-axes.index', $listing));
    $size = OptionAxis::where('listing_id', $listing->id)->where('name', 'Size')->sole();

    $this->actingAs($seller, 'seller')->post("/seller/listings/{$listing->id}/option-axes/{$size->id}/option-values", [
        'label' => '8x10', 'price' => '18.00', 'is_default' => '1', 'position' => 0,
    ]);
    $this->actingAs($seller, 'seller')->post("/seller/listings/{$listing->id}/option-axes/{$size->id}/option-values", [
        'label' => '11x14', 'price' => '24.00', 'position' => 1,
    ]);
    $elevenByFourteen = OptionValue::where('axis_id', $size->id)->where('label', '11x14')->sole();

    $this->actingAs($seller, 'seller')->post("/seller/listings/{$listing->id}/option-axes", [
        'name' => 'Frame', 'position' => 1,
    ]);
    $frame = OptionAxis::where('listing_id', $listing->id)->where('name', 'Frame')->sole();
    $this->actingAs($seller, 'seller')->post("/seller/listings/{$listing->id}/option-axes/{$frame->id}/option-values", [
        'label' => 'Unframed', 'surcharge' => '0.00', 'is_default' => '1', 'position' => 0,
    ]);
    $this->actingAs($seller, 'seller')->post("/seller/listings/{$listing->id}/option-axes/{$frame->id}/option-values", [
        'label' => 'Black frame', 'surcharge' => '32.00', 'position' => 1,
    ]);
    $blackFrame = OptionValue::where('axis_id', $frame->id)->where('label', 'Black frame')->sole();

    // Combinations: every Size x Frame pair exists.
    $this->actingAs($seller, 'seller')->post("/seller/listings/{$listing->id}/variants/generate")
        ->assertRedirect(route('seller.listings.variants.index', $listing));
    expect(Variant::where('listing_id', $listing->id)->count())->toBe(4);

    // The listing's own price is now derived from the default configuration.
    expect($listing->refresh()->price_cents)->toBe(1800);

    // Publish gates pass: every standalone option carries a price.
    $hub = $this->actingAs($seller, 'seller')->get("/seller/listings/{$listing->id}/edit");
    $hub->assertSee('Ready to go live — nothing is missing.')
        ->assertDontSee('Before this can go live')
        ->assertSee('each option priced on its own')
        ->assertSee('adds to your price');
    $this->actingAs($seller, 'seller')->post(route('seller.listings.status', $listing), ['status' => 'for_sale'])
        ->assertRedirect(route('seller.listings.index'));
    expect($listing->refresh()->status)->toBe(ListingStatus::ForSale);

    // Buyer: the page opens on the default (8x10, Unframed) at $18.00, the
    // non-selected 11x14 showing its own absolute price rather than a delta.
    $customer = $this->arriveAs($this->verifiedCustomer());
    $this->get("/art/{$listing->slug}")
        ->assertOk()
        ->assertSee('Size: 8x10', escape: false)
        ->assertSee('($24.00)', escape: false);

    // Picking 11x14 and a Black frame prices at $24.00 + $32.00 = $56.00.
    $withSelections = $this->get('/art/'.$listing->slug.'?'.http_build_query([
        'axis' => [$size->id => $elevenByFourteen->id, $frame->id => $blackFrame->id],
    ]));
    $withSelections->assertOk()
        ->assertSee('Size: 11x14', escape: false)
        ->assertSee('Frame: Black frame', escape: false)
        ->assertSee('$56.00');

    $this->post("/cart/{$listing->slug}", [
        'axis' => [$size->id => $elevenByFourteen->id, $frame->id => $blackFrame->id],
    ])->assertRedirect(route('shop.cart'));

    $placed = $this->post('/checkout', [
        'email' => $customer->email,
        'shipping_name' => 'Casey Whitfield',
        'shipping_line1' => '18 Harbour Road',
        'shipping_city' => 'Bristol',
        'shipping_region' => 'Bristol',
        'shipping_postal_code' => 'BS1 5TY',
        'shipping_country' => 'GB',
        'card_number' => '4242 4242 4242 4242',
    ]);

    $order = Order::sole();
    $placed->assertRedirect(route('shop.order', $order));
    expect($order->status)->toBe(OrderStatus::Paid);

    // The order snapshot: absolute Size line, signed Frame line, no base
    // line — the same rule the buyer page priced by.
    $item = $order->items()->sole();
    expect($item->price_breakdown_json)->toBe([
        ['label' => 'Size: 11x14', 'cents' => 2400],
        ['label' => 'Frame: Black frame', 'cents' => 3200],
    ])->and($item->lineTotal())->toBeMoney(5600);

    $this->get(route('shop.order', $order))
        ->assertOk()
        ->assertSee('Size:')->assertSee('11x14')
        ->assertSee('Frame:')->assertSee('Black frame')
        ->assertSee('$56.00');

    // A later price change on the purchased option never reaches the order
    // already placed at the old price.
    $elevenByFourteen->update(['price_cents' => 9900]);
    expect($order->items()->sole()->lineTotal())->toBeMoney(5600);
});
