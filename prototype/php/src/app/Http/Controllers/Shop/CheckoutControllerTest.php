<?php

declare(strict_types=1);

namespace App\Http\Controllers\Shop;

use App\Domain\Listings\ListingStatus;
use App\Domain\Orders\OrderStatus;
use App\Domain\RateLimiting\RateLimitValue;
use App\Models\Customer;
use App\Models\CustomerBlock;
use App\Models\ListingRemoval;
use App\Models\Order;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Config;
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

it('re-renders checkout naming the line archived while it sat there, not a redirect to the cart', function () use ($checkoutFields): void {
    $this->actingAs(Customer::factory()->create(['email' => 'shopper@example.com']), 'customer');
    $listing = $this->listing($this->seller(), ['slug' => 'harbour-at-dawn', 'title' => 'Harbour at Dawn', 'price_cents' => 24500]);
    $this->post('/cart/harbour-at-dawn');
    $listing->update(['status' => ListingStatus::Archived]);

    $response = $this->post('/checkout', $checkoutFields() + ['card_number' => '4242424242424242']);

    $response->assertStatus(422);
    $response->assertSee('Harbour at Dawn');
    $response->assertSee('no longer for sale');
    expect(Order::count())->toBe(0)
        ->and($listing->refresh()->quantity)->toBe(1);
});

it('re-renders checkout naming a removed line, even while it is still for sale', function () use ($checkoutFields): void {
    $this->actingAs(Customer::factory()->create(['email' => 'shopper@example.com']), 'customer');
    $listing = $this->listing($this->seller(), ['slug' => 'harbour-at-dawn', 'title' => 'Harbour at Dawn', 'price_cents' => 24500]);
    $this->post('/cart/harbour-at-dawn');
    ListingRemoval::factory()->create(['listing_id' => $listing->id, 'reason' => 'Under review.']);

    $response = $this->post('/checkout', $checkoutFields() + ['card_number' => '4242424242424242']);

    $response->assertStatus(422);
    $response->assertSee('Harbour at Dawn');
    $response->assertSee('no longer available');
    expect(Order::count())->toBe(0)
        ->and($listing->refresh()->quantity)->toBe(1);
});

it('names every reason two stale lines were refused, at once', function () use ($checkoutFields): void {
    $this->actingAs(Customer::factory()->create(['email' => 'shopper@example.com']), 'customer');
    $offSale = $this->listing($this->seller(), ['slug' => 'harbour-at-dawn', 'title' => 'Harbour at Dawn', 'price_cents' => 24500]);
    $soldOut = $this->listing($this->seller(), ['slug' => 'winter-elm', 'title' => 'Winter Elm', 'price_cents' => 24500, 'quantity' => 1]);
    $this->post('/cart/harbour-at-dawn');
    $this->post('/cart/winter-elm');
    $offSale->update(['status' => ListingStatus::Archived]);
    $this->orderFor($this->verifiedCustomer(), $soldOut->refresh());

    $response = $this->post('/checkout', $checkoutFields() + ['card_number' => '4242424242424242']);

    $response->assertStatus(422);
    $response->assertSee('Harbour at Dawn');
    $response->assertSee('no longer for sale');
    $response->assertSee('Winter Elm');
    $response->assertSee('sold out');
    expect(Order::count())->toBe(1);
});

it('keeps what the shopper typed when checkout is refused', function (): void {
    $this->actingAs(Customer::factory()->create(['email' => 'shopper@example.com']), 'customer');
    $listing = $this->listing($this->seller(), ['slug' => 'harbour-at-dawn', 'title' => 'Harbour at Dawn', 'price_cents' => 24500]);
    $this->post('/cart/harbour-at-dawn');
    $listing->update(['status' => ListingStatus::Archived]);

    $response = $this->post('/checkout', [
        'email' => 'shopper@example.com',
        'shipping_name' => 'Ada Lovelace',
        'shipping_line1' => '12 Analytical Way',
        'shipping_city' => 'London',
        'shipping_region' => 'Greater London',
        'shipping_postal_code' => 'EC1A 1BB',
        'shipping_country' => 'GB',
        'card_number' => '4242424242424242',
    ]);

    $response->assertStatus(422);
    $response->assertSee('value="12 Analytical Way"', escape: false);
});

it('keeps the card off the refusal it renders and out of the session', function () use ($checkoutFields): void {
    $this->actingAs(Customer::factory()->create(['email' => 'shopper@example.com']), 'customer');
    $listing = $this->listing($this->seller(), ['slug' => 'harbour-at-dawn', 'title' => 'Harbour at Dawn', 'price_cents' => 24500]);
    $this->post('/cart/harbour-at-dawn');
    $listing->update(['status' => ListingStatus::Archived]);

    $response = $this->post('/checkout', $checkoutFields() + [
        'card_number' => '4242424242424242',
        'card_expiry' => '04 / 30',
        'card_cvc' => '123',
    ]);

    $response->assertStatus(422);
    $response->assertDontSee('4242424242424242');
    expect(Session::getOldInput('card_number'))->toBeNull()
        ->and(Session::getOldInput('card_expiry'))->toBeNull()
        ->and(Session::getOldInput('card_cvc'))->toBeNull()
        ->and(Session::getOldInput('shipping_line1'))->toBe('12 Analytical Way');
});

it('carries every blocked line into the refused log line', function () use ($checkoutFields): void {
    $this->actingAs(Customer::factory()->create(['email' => 'shopper@example.com']), 'customer');
    $listing = $this->listing($this->seller(), ['slug' => 'harbour-at-dawn', 'title' => 'Harbour at Dawn', 'price_cents' => 24500]);
    $this->post('/cart/harbour-at-dawn');
    $listing->update(['status' => ListingStatus::Archived]);

    $log = CapturedStory::capture();

    $this->post('/checkout', $checkoutFields() + ['card_number' => '4242424242424242']);

    $refused = $log->line('order.place', 'refused');

    expect($refused['data'])->toHaveKey('blocked', [
        ['listing_id' => $listing->id, 'title' => 'Harbour at Dawn', 'reason' => 'off_sale'],
    ]);
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

it('trips the checkout limit, re-rendering the checkout form with no order placed', function () use ($fillCart, $checkoutFields): void {
    Config::set('rate_limits.checkout', RateLimitValue::parse('1/1m', 'RATE_LIMIT_CHECKOUT'));
    $this->actingAs(Customer::factory()->create(['email' => 'shopper@example.com']), 'customer');
    $fillCart();
    $this->post('/checkout', $checkoutFields() + ['card_number' => '4242424242424242']);
    $this->listing($this->seller(), ['slug' => 'second-piece', 'price_cents' => 5000]);
    $this->post('/cart/second-piece');

    $response = $this->post('/checkout', $checkoutFields() + ['card_number' => '4242424242424242']);

    $response->assertStatus(429);
    $response->assertHeader('Retry-After');
    $response->assertSee('Too many requests', escape: false);
    expect(Order::count())->toBe(1);
});

it('resets the checkout limit once its window passes', function () use ($fillCart, $checkoutFields): void {
    Config::set('rate_limits.checkout', RateLimitValue::parse('1/1m', 'RATE_LIMIT_CHECKOUT'));
    $this->actingAs(Customer::factory()->create(['email' => 'shopper@example.com']), 'customer');
    $fillCart();
    $this->post('/checkout', $checkoutFields() + ['card_number' => '4242424242424242']);

    $this->travel(61)->seconds();
    $this->listing($this->seller(), ['slug' => 'second-piece', 'price_cents' => 5000]);
    $this->post('/cart/second-piece');
    $response = $this->post('/checkout', $checkoutFields() + ['card_number' => '4242424242424242']);

    $response->assertStatus(302);
    expect(Order::count())->toBe(2);
});

it('logs the checkout trip as rate_limit.exceed at warn, keyed by the customer', function () use ($fillCart, $checkoutFields): void {
    Config::set('rate_limits.checkout', RateLimitValue::parse('1/1m', 'RATE_LIMIT_CHECKOUT'));
    $shopper = Customer::factory()->create(['email' => 'shopper@example.com']);
    $this->actingAs($shopper, 'customer');
    $fillCart();
    $this->post('/checkout', $checkoutFields() + ['card_number' => '4242424242424242']);
    $this->listing($this->seller(), ['slug' => 'second-piece', 'price_cents' => 5000]);
    $this->post('/cart/second-piece');

    $log = CapturedStory::capture();
    $this->post('/checkout', $checkoutFields() + ['card_number' => '4242424242424242']);

    $line = $log->line('rate_limit.exceed', 'refused');

    /** @var array<string, mixed> $data */
    $data = $line['data'];

    expect($line['level'])->toBe('warn')
        ->and($data['limit'])->toBe('checkout')
        ->and($data['key'])->toBe($shopper->id);
});

it('trips the magic-link budget on a guest checkout before placing an order, spending it by email and by ip', function () use ($fillCart, $checkoutFields): void {
    Config::set('rate_limits.magic_link_request', RateLimitValue::parse('1/15m', 'RATE_LIMIT_MAGIC_LINK_REQUEST'));
    $this->visitor();
    $fillCart();
    $this->post('/checkout', $checkoutFields());
    $this->listing($this->seller(), ['slug' => 'second-piece', 'price_cents' => 5000]);
    $this->post('/cart/second-piece');

    $response = $this->post('/checkout', $checkoutFields());

    $response->assertStatus(429);
    expect(Order::count())->toBe(1);
});
