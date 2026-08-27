<?php

declare(strict_types=1);

namespace App\Http\Controllers\Shop;

use App\Actions\Configurator\AddModifierOption;
use App\Actions\Configurator\AddOptionValue;
use App\Actions\Configurator\AddQuantityBreak;
use App\Actions\Configurator\AddUnit;
use App\Actions\Configurator\CreateModifier;
use App\Actions\Configurator\CreateOptionAxis;
use App\Actions\Configurator\CreateVariant;
use App\Actions\Configurator\GenerateVariants;
use App\Actions\Configurator\ScopeModifier;
use App\Domain\Configurator\ModifierKind;
use App\Domain\Listings\ListingEventType;
use App\Domain\Listings\ListingStatus;
use App\Models\CartItem;
use App\Models\Customer;
use App\Models\CustomerBlock;
use App\Models\ListingEvent;
use App\Models\ListingRemoval;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Session;

it('adds a listing to the cart and records the event', function (): void {
    $visitor = $this->visitor();
    $listing = $this->listing($this->seller(), ['slug' => 'harbour-at-dawn']);

    $response = $this->post('/cart/harbour-at-dawn');

    $response->assertRedirect(route('shop.cart'));
    $item = CartItem::sole();
    expect($item->listing_id)->toBe($listing->id)
        ->and($item->quantity)->toBe(1)
        ->and($item->cart->customer_id)->toBe($visitor->id)
        ->and(ListingEvent::sole()->type)->toBe(ListingEventType::CartAdd);
});

it('sends a blocked customer back with the reason instead of adding to the cart', function (): void {
    $visitor = $this->visitor();
    CustomerBlock::factory()->create(['customer_id' => $visitor->id, 'reason' => 'Chargeback fraud.']);
    $this->listing($this->seller(), ['slug' => 'harbour-at-dawn']);

    $response = $this->from(route('shop.listing', 'harbour-at-dawn'))
        ->followingRedirects()
        ->post('/cart/harbour-at-dawn');

    $response->assertOk();
    $response->assertSee('Buying is unavailable while your account is blocked: Chargeback fraud.');
    expect(CartItem::count())->toBe(0);
});

it('refuses a stale slug for a listing an admin has removed', function (): void {
    $this->visitor();
    $listing = $this->listing($this->seller(), ['slug' => 'harbour-at-dawn']);
    ListingRemoval::factory()->create(['listing_id' => $listing->id]);

    $response = $this->from(route('shop.home'))->post('/cart/harbour-at-dawn');

    $response->assertRedirect(route('shop.home'));
    $response->assertSessionHasErrors();
    expect(CartItem::count())->toBe(0);
});

it('shows the lines and the subtotal', function (): void {
    $this->visitor();
    $seller = $this->seller();
    $this->listing($seller, ['slug' => 'harbour-at-dawn', 'title' => 'Harbour at Dawn', 'price_cents' => 24500]);
    $this->listing($seller, ['slug' => 'winter-elm', 'title' => 'Winter Elm', 'price_cents' => 5500]);
    $this->post('/cart/harbour-at-dawn');
    $this->post('/cart/winter-elm');

    $response = $this->get('/cart');

    $response->assertOk();
    $response->assertSee('Harbour at Dawn');
    $response->assertSee('Winter Elm');
    $response->assertSee('$300.00');
});

it('removes a line', function (): void {
    $this->visitor();
    $this->listing($this->seller(), ['slug' => 'harbour-at-dawn']);
    $this->post('/cart/harbour-at-dawn');
    $item = CartItem::sole();

    $response = $this->delete("/cart/items/{$item->id}");

    $response->assertRedirect(route('shop.cart'));
    expect(CartItem::count())->toBe(0);
});

it('renders the remove button as a DELETE form', function (): void {
    $this->visitor();
    $this->listing($this->seller(), ['slug' => 'harbour-at-dawn']);
    $this->post('/cart/harbour-at-dawn');

    $response = $this->get('/cart');

    $response->assertSee('<input type="hidden" name="_method" value="DELETE">', escape: false);
});

it('removes only the configuration asked for, leaving a second configuration of the same listing in the cart', function (): void {
    $this->visitor();
    $listing = $this->listing($this->seller(), ['slug' => 'ring']);
    $metal = app(CreateOptionAxis::class)($listing, 'Metal');
    app(AddOptionValue::class)($metal, 'Gold', 0, isDefault: true);
    $silver = app(AddOptionValue::class)($metal, 'Silver', 0);
    app(GenerateVariants::class)($listing);
    $this->post('/cart/ring');
    $this->post('/cart/ring', ['axis' => [$metal->id => $silver->id]]);
    expect(CartItem::count())->toBe(2);

    $toRemove = CartItem::query()->orderBy('id')->firstOrFail();
    $kept = CartItem::query()->orderBy('id')->skip(1)->firstOrFail();

    $response = $this->delete("/cart/items/{$toRemove->id}");

    $response->assertRedirect(route('shop.cart'));
    expect(CartItem::count())->toBe(1)
        ->and(CartItem::sole()->id)->toBe($kept->id);
});

it('answers not found removing another visitors cart line', function (): void {
    $this->listing($this->seller(), ['slug' => 'harbour-at-dawn']);
    $this->arriveAs($this->anonymousCustomer());
    $this->post('/cart/harbour-at-dawn');
    $item = CartItem::sole();

    $intruder = $this->arriveAs($this->anonymousCustomer());
    $response = $this->delete("/cart/items/{$item->id}");

    $response->assertNotFound();
    expect(CartItem::count())->toBe(1);
});

it('answers not found removing a cart item id that matches nothing', function (): void {
    $this->visitor();

    $response = $this->delete('/cart/items/cti_00000000000000000000000001');

    $response->assertNotFound();
});

it('refuses a listing that is not for sale', function (): void {
    $this->visitor();
    $this->listing($this->seller(), [
        'slug' => 'sold-vase',
        'title' => 'Sold Vase',
        'status' => ListingStatus::Sold,
        'quantity' => 0,
    ]);

    $response = $this->from(route('shop.listing', 'sold-vase'))
        ->followingRedirects()
        ->post('/cart/sold-vase');

    $response->assertOk();
    $response->assertSee('That listing is no longer for sale.');
    expect(CartItem::count())->toBe(0);
});

it('says an empty cart is empty', function (): void {
    $response = $this->get('/cart');

    $response->assertOk();
    $response->assertSee('Your cart is empty');
});

it('survives the merge when the cart was filled before signing in', function (): void {
    $this->visitor();
    Customer::factory()->create(['email' => 'shopper@example.com']);
    $this->listing($this->seller(), ['slug' => 'harbour-at-dawn', 'title' => 'Harbour at Dawn']);
    $this->post('/cart/harbour-at-dawn');

    $this->post('/login', ['email' => 'shopper@example.com']);
    $this->get(Arr::string(Session::all(), 'debug_magic_link'));

    $response = $this->get('/cart');

    $response->assertSee('Harbour at Dawn');
    expect(CartItem::count())->toBe(1);
});

it('marks a line the seller took off sale and disables checkout', function (): void {
    $this->visitor();
    $listing = $this->listing($this->seller(), ['slug' => 'harbour-at-dawn', 'title' => 'Harbour at Dawn']);
    $this->post('/cart/harbour-at-dawn');
    $listing->update(['status' => ListingStatus::Archived]);

    $response = $this->get('/cart');

    $response->assertOk();
    $response->assertSee('Harbour at Dawn');
    $response->assertSee('No longer for sale');
    $response->assertSee('<button type="button" disabled', escape: false);
});

it('marks a line another buyer took and disables checkout', function (): void {
    $this->visitor();
    $listing = $this->listing($this->seller(), ['slug' => 'winter-elm', 'title' => 'Winter Elm', 'quantity' => 1]);
    $this->post('/cart/winter-elm');
    $this->orderFor($this->verifiedCustomer(), $listing->refresh());

    $response = $this->get('/cart');

    $response->assertOk();
    $response->assertSee('Winter Elm');
    $response->assertSee('Sold out');
    $response->assertSee('<button type="button" disabled', escape: false);
});

it('marks every blocked line at once', function (): void {
    $this->visitor();
    $offSale = $this->listing($this->seller(), ['slug' => 'harbour-at-dawn', 'title' => 'Harbour at Dawn']);
    $soldOut = $this->listing($this->seller(), ['slug' => 'winter-elm', 'title' => 'Winter Elm', 'quantity' => 1]);
    $this->post('/cart/harbour-at-dawn');
    $this->post('/cart/winter-elm');
    $offSale->update(['status' => ListingStatus::Archived]);
    $this->orderFor($this->verifiedCustomer(), $soldOut->refresh());

    $response = $this->get('/cart');

    $response->assertOk();
    $response->assertSee('No longer for sale');
    $response->assertSee('Sold out');
});

it('leaves a purchasable line unmarked and checkout enabled', function (): void {
    $this->visitor();
    $this->listing($this->seller(), ['slug' => 'harbour-at-dawn', 'title' => 'Harbour at Dawn']);
    $this->post('/cart/harbour-at-dawn');

    $response = $this->get('/cart');

    $response->assertOk();
    $response->assertDontSee('No longer for sale');
    $response->assertSee('href="'.route('shop.checkout').'"', escape: false);
    $response->assertDontSee('<button type="button" disabled', escape: false);
});

it('adds the rings configuration to the cart, itemized and merged on a repeat add', function (): void {
    $this->visitor();
    $listing = $this->listing($this->seller(), ['slug' => 'ring', 'title' => 'Engraved Signet Ring', 'price_cents' => 12000]);
    $metal = app(CreateOptionAxis::class)($listing, 'Metal');
    app(AddOptionValue::class)($metal, 'Gold', 0, isDefault: true);
    $roseGold = app(AddOptionValue::class)($metal, 'Rose Gold', 800);
    $engraving = app(CreateOptionAxis::class)($listing, 'Engraving');
    app(AddOptionValue::class)($engraving, 'No Engraving', 0, isDefault: true);
    $outside = app(AddOptionValue::class)($engraving, 'Outside Only', 500);
    app(GenerateVariants::class)($listing);
    $font = app(CreateModifier::class)($listing, ModifierKind::Select, 'Engraving Font', required: true);
    $block = app(AddModifierOption::class)($font, 'Block', 0, 0);
    $text = app(CreateModifier::class)($listing, ModifierKind::Text, 'Engraving Text', required: true, charLimit: 20);
    app(ScopeModifier::class)($font, [$outside]);
    app(ScopeModifier::class)($text, [$outside]);

    $post = [
        'axis' => [$metal->id => $roseGold->id, $engraving->id => $outside->id],
        'modifier' => [$font->id => $block->id, $text->id => 'ADA'],
    ];

    $this->post('/cart/ring', $post);
    $response = $this->post('/cart/ring', $post);

    $response->assertRedirect(route('shop.cart'));
    $item = CartItem::sole();
    expect($item->quantity)->toBe(2)
        ->and($item->configuration_json)->toBe([
            ['axisId' => $metal->id, 'axisName' => 'Metal', 'optionValueId' => $roseGold->id, 'optionValueLabel' => 'Rose Gold'],
            ['axisId' => $engraving->id, 'axisName' => 'Engraving', 'optionValueId' => $outside->id, 'optionValueLabel' => 'Outside Only'],
        ]);
    $answers = $item->answers_json ?? [];
    expect($answers[$text->id]['answer'])->toBe('ADA');

    $cartPage = $this->get('/cart');
    $cartPage->assertOk();
    $cartPage->assertSee('Metal:');
    $cartPage->assertSee('Rose Gold');
    $cartPage->assertSee('Engraving Text:');
    $cartPage->assertSee('ADA');
    $cartPage->assertSee('$266.00');
});

it('keeps two different ring configurations as separate cart lines', function (): void {
    $this->visitor();
    $listing = $this->listing($this->seller(), ['slug' => 'ring', 'price_cents' => 12000]);
    $metal = app(CreateOptionAxis::class)($listing, 'Metal');
    app(AddOptionValue::class)($metal, 'Gold', 0, isDefault: true);
    $silver = app(AddOptionValue::class)($metal, 'Silver', 0);
    app(GenerateVariants::class)($listing);

    $this->post('/cart/ring');
    $this->post('/cart/ring', ['axis' => [$metal->id => $silver->id]]);

    expect(CartItem::count())->toBe(2);
});

it('hides the mugs personalization box and never charges its flat fee when blank', function (): void {
    $this->visitor();
    $listing = $this->listing($this->seller(), ['slug' => 'mug', 'price_cents' => 1800]);
    $personalization = app(CreateOptionAxis::class)($listing, 'Personalization');
    app(AddOptionValue::class)($personalization, 'Blank', 0, isDefault: true);
    $personalized = app(AddOptionValue::class)($personalization, 'Personalized', 300);
    app(GenerateVariants::class)($listing);
    $text = app(CreateModifier::class)($listing, ModifierKind::Text, 'Personalization Text', required: true, charLimit: 16);
    app(ScopeModifier::class)($text, [$personalized]);

    $response = $this->post('/cart/mug');

    $response->assertRedirect(route('shop.cart'));
    $item = CartItem::sole();
    expect($item->answers_json)->toBeNull()
        ->and($item->toLine()->total())->toBeMoney(1800);
});

it('refuses to add the tables sparse not-offered combination to the cart', function (): void {
    $this->visitor();
    $listing = $this->listing($this->seller(), ['slug' => 'table', 'price_cents' => 80000]);
    $length = app(CreateOptionAxis::class)($listing, 'Length');
    $l36 = app(AddOptionValue::class)($length, '36 in', 0, isDefault: true);
    $l48 = app(AddOptionValue::class)($length, '48 in', 0);
    $width = app(CreateOptionAxis::class)($listing, 'Width');
    $w24 = app(AddOptionValue::class)($width, '24 in', 0, isDefault: true);
    $w30 = app(AddOptionValue::class)($width, '30 in', 0);
    $createVariant = app(CreateVariant::class);
    $createVariant($listing, [$l36, $w24], priceOverrideCents: 80000);
    $createVariant($listing, [$l48, $w30], priceOverrideCents: 110000);

    $response = $this->from(route('shop.listing', 'table'))
        ->followingRedirects()
        ->post('/cart/table', ['axis' => [$length->id => $l48->id]]);

    $response->assertOk();
    $response->assertSee('That configuration is no longer available.');
    expect(CartItem::count())->toBe(0);
});

it('claims a specific candlestick unit and keeps a second add of the same unit at one', function (): void {
    $this->visitor();
    $listing = $this->listing($this->seller(), ['slug' => 'candlesticks', 'price_cents' => 4500]);
    $variant = app(CreateVariant::class)($listing, [], isSerialized: true);
    $unit = app(AddUnit::class)($variant, '#1', conditionNote: 'Excellent estate condition', priceOverrideCents: 3500);
    app(AddUnit::class)($variant, '#2');

    $this->post('/cart/candlesticks', ['unit' => $unit->id]);
    $response = $this->post('/cart/candlesticks', ['unit' => $unit->id, 'quantity' => 5]);

    $response->assertRedirect(route('shop.cart'));
    $item = CartItem::sole();
    expect($item->unit_id)->toBe($unit->id)
        ->and($item->quantity)->toBe(1)
        ->and($item->toLine()->total())->toBeMoney(3500);

    $cartPage = $this->get('/cart');
    $cartPage->assertSee('Piece:');
    $cartPage->assertSee('#1');
});

it('prices the wedding invitations quantity break into the cart line', function (): void {
    $this->visitor();
    $listing = $this->listing($this->seller(), ['slug' => 'invitations', 'price_cents' => 300]);
    $size = app(CreateOptionAxis::class)($listing, 'Size');
    app(AddOptionValue::class)($size, '4x6 in', 0, isDefault: true);
    app(GenerateVariants::class)($listing);
    $paperStock = app(CreateModifier::class)($listing, ModifierKind::Select, 'Paper Stock', required: true);
    $standard = app(AddModifierOption::class)($paperStock, 'Standard', 0, 0);
    app(AddQuantityBreak::class)($listing, 50, 500);
    app(AddQuantityBreak::class)($listing, 100, 1000);

    $response = $this->post('/cart/invitations', ['quantity' => 100, 'modifier' => [$paperStock->id => $standard->id]]);

    $response->assertRedirect(route('shop.cart'));
    $item = CartItem::sole();
    expect($item->quantity)->toBe(100)
        ->and($item->toLine()->total())->toBeMoney(27000);

    $cartPage = $this->get('/cart');
    $cartPage->assertSee('$270.00');
});
