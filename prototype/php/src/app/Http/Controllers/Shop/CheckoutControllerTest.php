<?php

declare(strict_types=1);

namespace App\Http\Controllers\Shop;

use App\Domain\Listings\ListingStatus;
use App\Domain\Orders\OrderStatus;
use App\Models\Customer;
use App\Models\CustomerBlock;
use App\Models\Order;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Session;
use Tests\CapturedStory;

$fillCart = function (): void {
    test()->listing(test()->seller(), ['slug' => 'harbour-at-dawn', 'price_cents' => 24500]);
    test()->post('/cart/harbour-at-dawn');
};

/**
 * @return array<string, string>
 */
$checkoutFields = function (): array {
    return [
        'email' => 'guest@example.com',
        'shipping_name' => 'Ada Lovelace',
        'shipping_line1' => '12 Analytical Way',
        'shipping_city' => 'London',
        'shipping_region' => 'Greater London',
        'shipping_postal_code' => 'EC1A 1BB',
        'shipping_country' => 'GB',
    ];
};

it('sends an empty cart back to the cart page', function (): void {
    $this->get('/checkout')->assertRedirect(route('shop.cart'));
});

it('refuses to place an order from an empty cart', function () use ($checkoutFields): void {
    $response = $this->post('/checkout', $checkoutFields());

    $response->assertRedirect(route('shop.cart'));
    expect(Order::count())->toBe(0);
});

it('prefills and locks the address of a signed in customer', function () use ($fillCart): void {
    $this->actingAs(Customer::factory()->create(['email' => 'shopper@example.com']), 'customer');
    $fillCart();

    $response = $this->get('/checkout');

    $response->assertOk();
    $response->assertSee('value="shopper@example.com" readonly', escape: false);
    $response->assertSee('name="card_number"', escape: false);
});

it('gives a guest no card fields before verifying', function () use ($fillCart): void {
    $this->visitor();
    $fillCart();

    $response = $this->get('/checkout');

    $response->assertOk();
    $response->assertDontSee('name="card_number"', escape: false);
});

it('places an order that waits for verification for a guest', function () use ($fillCart, $checkoutFields): void {
    $this->visitor();
    $fillCart();

    $response = $this->post('/checkout', $checkoutFields());

    $order = Order::sole();
    expect($order->status)->toBe(OrderStatus::PendingVerification)
        ->and($order->email)->toBe('guest@example.com')
        ->and($order->shipping_name)->toBe('Ada Lovelace');
    $response->assertRedirect(route('shop.order', $order));
    $response->assertSessionHas('debug_magic_link');
});

it('explains on the order page that a link was sent', function () use ($fillCart, $checkoutFields): void {
    $this->visitor();
    $fillCart();

    $response = $this->followingRedirects()->post('/checkout', $checkoutFields());

    $response->assertSee('Check your email');
    $response->assertSee('/auth/magic/', escape: false);
});

it('carries the guest order to their account and pays it on verification', function () use ($fillCart, $checkoutFields): void {
    $this->visitor();
    $shopper = Customer::factory()->create(['email' => 'guest@example.com']);
    $fillCart();
    $this->post('/checkout', $checkoutFields());
    $order = Order::sole();

    $this->get(Arr::string(Session::all(), 'debug_magic_link'))->assertRedirect(route('shop.order.pay', $order));
    expect($order->refresh()->customer_id)->toBe($shopper->id);

    $response = $this->post(route('shop.order.pay', $order), ['card_number' => '4242 4242 4242 4242']);

    $response->assertRedirect(route('shop.order', $order));
    expect($order->refresh()->status)->toBe(OrderStatus::Paid);
});

it('lets a verified customer pay as they place the order', function () use ($fillCart, $checkoutFields): void {
    $this->actingAs(Customer::factory()->create(['email' => 'shopper@example.com']), 'customer');
    $fillCart();

    $response = $this->post('/checkout', $checkoutFields() + ['card_number' => '4242424242424242']);

    $order = Order::sole();
    expect($order->status)->toBe(OrderStatus::Paid)
        ->and($order->email)->toBe('shopper@example.com');
    $response->assertRedirect(route('shop.order', $order));
});

it('leaves the order unpaid with a reason when the card is declined', function () use ($fillCart, $checkoutFields): void {
    $this->actingAs(Customer::factory()->create(), 'customer');
    $fillCart();

    $response = $this->followingRedirects()
        ->post('/checkout', $checkoutFields() + ['card_number' => '4000000000000002']);

    expect(Order::sole()->status)->toBe(OrderStatus::PaymentFailed);
    $response->assertSee('Your card was declined.');
});

it('sends a blocked customer to the cart with the reason instead of placing an order', function () use ($fillCart, $checkoutFields): void {
    $shopper = Customer::factory()->create(['email' => 'shopper@example.com']);
    $this->actingAs($shopper, 'customer');
    $fillCart();
    CustomerBlock::factory()->create(['customer_id' => $shopper->id, 'reason' => 'Chargeback fraud.']);

    $response = $this->post('/checkout', $checkoutFields() + ['card_number' => '4242424242424242']);

    $response->assertRedirect(route('shop.cart'));
    $response->assertSessionHasErrors();
    expect(Order::count())->toBe(0);
});

it('sends the shopper back to the cart when a line was archived while it sat there', function () use ($checkoutFields): void {
    $this->actingAs(Customer::factory()->create(['email' => 'shopper@example.com']), 'customer');
    $listing = $this->listing($this->seller(), ['slug' => 'harbour-at-dawn', 'title' => 'Harbour at Dawn', 'price_cents' => 24500]);
    $this->post('/cart/harbour-at-dawn');
    $listing->update(['status' => ListingStatus::Archived]);

    $response = $this->post('/checkout', $checkoutFields() + ['card_number' => '4242424242424242']);

    $response->assertRedirect(route('shop.cart'));
    $response->assertSessionHasErrors();
    expect(Order::count())->toBe(0)
        ->and($listing->refresh()->quantity)->toBe(1);
});

it('names the item that sold to someone else and marks it on the cart page', function () use ($checkoutFields): void {
    $this->actingAs(Customer::factory()->create(['email' => 'shopper@example.com']), 'customer');
    $listing = $this->listing($this->seller(), ['slug' => 'winter-elm', 'title' => 'Winter Elm', 'price_cents' => 24500, 'quantity' => 1]);
    $this->post('/cart/winter-elm');
    $this->orderFor($this->verifiedCustomer(), $listing);

    $response = $this->followingRedirects()->post('/checkout', $checkoutFields() + ['card_number' => '4242424242424242']);

    $response->assertOk();
    $response->assertSee('“Winter Elm” is no longer for sale.');
    $response->assertSee('No longer available');
    expect(Order::count())->toBe(1);
});

it('tells the story of one checkout in order, under one request and one unit of work', function () use ($fillCart, $checkoutFields): void {
    $this->actingAs(Customer::factory()->create(['email' => 'shopper@example.com']), 'customer');
    $fillCart();

    $log = CapturedStory::capture();

    $this->post('/checkout', $checkoutFields() + ['card_number' => '4242424242424242']);

    $told = array_values(array_filter(
        $log->outline(),
        fn (string $line): bool => str_starts_with($line, 'http.request ') || str_starts_with($line, 'order.place '),
    ));

    expect($told)->toBe([
        'http.request will',
        'order.place will',
        'order.place did',
        'http.request did',
    ]);

    $placed = $log->linesFor('order.place');

    expect(array_unique($log->values('request_id')))->toHaveCount(1)
        ->and($placed[0]['txn_id'])->toBeString()
        ->and($placed[1]['txn_id'])->toBe($placed[0]['txn_id'])
        ->and($log->line('http.request', 'will'))->not->toHaveKey('txn_id');
});

it('carries the order through the payment story that follows placing it', function () use ($fillCart, $checkoutFields): void {
    $this->actingAs(Customer::factory()->create(['email' => 'shopper@example.com']), 'customer');
    $fillCart();

    $log = CapturedStory::capture();

    $this->post('/checkout', $checkoutFields() + ['card_number' => '4242424242424242']);

    $order = Order::sole();
    $paid = $log->line('order.pay', 'did');

    expect($paid['data'])->toBe([
        'order_id' => $order->id,
        'amount_cents' => 24500,
        'status' => 'paid',
    ])
        ->and($log->line('ledger.write', 'did')['txn_id'])->toBe($paid['txn_id']);
});

it('reads a declined card as a refusal naming why', function () use ($fillCart, $checkoutFields): void {
    $this->actingAs(Customer::factory()->create(['email' => 'shopper@example.com']), 'customer');
    $fillCart();

    $log = CapturedStory::capture();

    $this->post('/checkout', $checkoutFields() + ['card_number' => '4000000000000002']);

    $refused = $log->line('order.pay', 'refused');

    expect($refused['level'])->toBe('info')
        ->and($refused['data'])->toHaveKey('decline_reason', 'generic_decline');
});

it('reads a checkout the core turned down as a refusal, not a failure', function () use ($checkoutFields): void {
    $shopper = Customer::factory()->create(['email' => 'shopper@example.com']);
    $this->actingAs($shopper, 'customer');
    $this->listing($this->seller(), ['slug' => 'harbour-at-dawn', 'price_cents' => 24500]);
    $this->post('/cart/harbour-at-dawn');
    CustomerBlock::factory()->create(['customer_id' => $shopper->id, 'reason' => 'Chargeback fraud.']);

    $log = CapturedStory::capture();

    $this->post('/checkout', $checkoutFields() + ['card_number' => '4242424242424242']);

    $refused = $log->line('order.place', 'refused');

    expect($refused['level'])->toBe('info')
        ->and($refused['msg'])->toContain('blocked')
        ->and($log->linesFor('order.place'))->toHaveCount(2);
});

it('keeps the address and the card out of every line the checkout writes', function () use ($fillCart, $checkoutFields): void {
    $this->actingAs(Customer::factory()->create(['email' => 'shopper@example.com']), 'customer');
    $fillCart();

    $log = CapturedStory::capture();

    $this->post('/checkout', $checkoutFields() + ['card_number' => '4242424242424242']);

    $written = $log->raw();

    expect($written)->not->toContain('guest@example.com');
    expect($written)->not->toContain('shopper@example.com');
    expect($written)->not->toContain('4242424242424242');
    expect($written)->not->toContain('12 Analytical Way');
    expect($written)->not->toContain('EC1A 1BB');
});
