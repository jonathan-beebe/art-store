<?php

declare(strict_types=1);

namespace App\Http\Controllers\Shop;

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

    $response = $this->delete('/cart/harbour-at-dawn');

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
