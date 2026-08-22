<?php

namespace App\Http\Controllers\Shop;

use App\Domain\Orders\OrderStatus;
use App\Models\Customer;
use App\Models\Order;
use Tests\StorefrontTestCase;

final class CheckoutControllerTest extends StorefrontTestCase
{
    public function test_an_empty_cart_goes_back_to_the_cart_page(): void
    {
        $this->get('/checkout')->assertRedirect(route('shop.cart'));
    }

    public function test_it_prefills_and_locks_the_address_of_a_signed_in_customer(): void
    {
        $this->actingAs(Customer::factory()->create(['email' => 'shopper@example.com']), 'customer');
        $this->fillCart();

        $response = $this->get('/checkout');

        $response->assertOk();
        $response->assertSee('value="shopper@example.com"', escape: false);
        $response->assertSee('readonly', escape: false);
        $response->assertSee('name="card_number"', escape: false);
    }

    public function test_a_guest_gets_no_card_fields_before_verifying(): void
    {
        $this->visitor();
        $this->fillCart();

        $response = $this->get('/checkout');

        $response->assertOk();
        $response->assertDontSee('name="card_number"', escape: false);
    }

    public function test_a_guest_places_an_order_that_waits_for_verification(): void
    {
        $this->visitor();
        $this->fillCart();

        $response = $this->post('/checkout', $this->checkoutFields());

        $order = Order::sole();
        $this->assertSame(OrderStatus::PendingVerification, $order->status);
        $this->assertSame('guest@example.com', $order->email);
        $this->assertSame('Ada Lovelace', $order->shipping_name);
        $response->assertRedirect(route('shop.order', $order));
        $response->assertSessionHas('debug_magic_link');
    }

    public function test_the_order_page_explains_that_a_link_was_sent(): void
    {
        $this->visitor();
        $this->fillCart();

        $response = $this->followingRedirects()->post('/checkout', $this->checkoutFields());

        $response->assertSee('Check your email');
        $response->assertSee('/auth/magic/', escape: false);
    }

    public function test_verifying_carries_the_guest_order_to_their_account_and_pays_it(): void
    {
        $this->visitor();
        $shopper = Customer::factory()->create(['email' => 'guest@example.com']);
        $this->fillCart();
        $this->post('/checkout', $this->checkoutFields());
        $order = Order::sole();

        $this->get(session('debug_magic_link'))->assertRedirect(route('shop.order.pay', $order));
        $this->assertSame($shopper->id, $order->fresh()->customer_id);

        $response = $this->post(route('shop.order.pay', $order), ['card_number' => '4242 4242 4242 4242']);

        $response->assertRedirect(route('shop.order', $order));
        $this->assertSame(OrderStatus::Paid, $order->fresh()->status);
    }

    public function test_a_verified_customer_pays_as_they_place_the_order(): void
    {
        $this->actingAs(Customer::factory()->create(['email' => 'shopper@example.com']), 'customer');
        $this->fillCart();

        $response = $this->post('/checkout', $this->checkoutFields() + ['card_number' => '4242424242424242']);

        $order = Order::sole();
        $this->assertSame(OrderStatus::Paid, $order->status);
        $this->assertSame('shopper@example.com', $order->email);
        $response->assertRedirect(route('shop.order', $order));
    }

    public function test_a_verified_customer_must_give_a_card(): void
    {
        $this->actingAs(Customer::factory()->create(), 'customer');
        $this->fillCart();

        $response = $this->post('/checkout', $this->checkoutFields());

        $response->assertSessionHasErrors('card_number');
        $this->assertSame(0, Order::count());
    }

    public function test_a_declined_card_leaves_the_order_unpaid_with_a_reason(): void
    {
        $this->actingAs(Customer::factory()->create(), 'customer');
        $this->fillCart();

        $response = $this->followingRedirects()
            ->post('/checkout', $this->checkoutFields() + ['card_number' => '4000000000000002']);

        $this->assertSame(OrderStatus::PaymentFailed, Order::sole()->status);
        $response->assertSee('Your card was declined.');
    }

    private function fillCart(): void
    {
        $this->listing($this->seller(), ['slug' => 'harbour-at-dawn', 'price_cents' => 24500]);
        $this->post('/cart/harbour-at-dawn');
    }

    /**
     * @return array<string, string>
     */
    private function checkoutFields(): array
    {
        return [
            'email' => 'guest@example.com',
            'shipping_name' => 'Ada Lovelace',
            'shipping_line1' => '12 Analytical Way',
            'shipping_city' => 'London',
            'shipping_region' => 'Greater London',
            'shipping_postal_code' => 'EC1A 1BB',
            'shipping_country' => 'GB',
        ];
    }
}
