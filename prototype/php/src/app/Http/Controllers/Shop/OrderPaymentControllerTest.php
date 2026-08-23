<?php

declare(strict_types=1);

namespace App\Http\Controllers\Shop;

use App\Actions\Orders\FinalizeOrder;
use App\Domain\Orders\OrderStatus;
use App\Models\Customer;
use App\Models\Order;
use Tests\StorefrontTestCase;

final class OrderPaymentControllerTest extends StorefrontTestCase
{
    public function test_it_asks_a_verified_customer_for_a_card(): void
    {
        $shopper = $this->arriveAs($this->verifiedCustomer());
        $order = $this->unpaidOrderFor($shopper);

        $response = $this->get(route('shop.order.pay', $order));

        $response->assertOk();
        $response->assertSee('name="card_number"', escape: false);
    }

    public function test_it_sends_an_unverified_visitor_to_sign_in_first(): void
    {
        $visitor = $this->visitor();
        $order = $this->unpaidOrderFor($visitor);

        $response = $this->get(route('shop.order.pay', $order));

        $response->assertRedirect(route('auth.customer.login', [
            'redirect_to' => route('shop.order.pay', $order, absolute: false),
        ]));
    }

    public function test_another_customer_cannot_pay_the_order(): void
    {
        $order = $this->unpaidOrderFor($this->verifiedCustomer());
        $this->arriveAs($this->verifiedCustomer());

        $this->get(route('shop.order.pay', $order))->assertNotFound();
    }

    public function test_a_paid_order_goes_back_to_the_order_page(): void
    {
        $shopper = $this->arriveAs($this->verifiedCustomer());
        $order = $this->unpaidOrderFor($shopper);
        app(FinalizeOrder::class)($order, '4242424242424242', $this->moment('2026-08-20 10:00:00'));

        $this->get(route('shop.order.pay', $order))->assertRedirect(route('shop.order', $order));
    }

    public function test_a_declined_card_is_reported_and_the_retry_pays(): void
    {
        $shopper = $this->arriveAs($this->verifiedCustomer());
        $order = $this->unpaidOrderFor($shopper);

        $declined = $this->followingRedirects()
            ->post(route('shop.order.pay', $order), ['card_number' => '4000 0000 0000 0002']);

        $declined->assertSee('Your card was declined.');
        $declined->assertSee('name="card_number"', escape: false);
        $this->assertSame(OrderStatus::PaymentFailed, $order->fresh()->status);

        $retried = $this->post(route('shop.order.pay', $order), ['card_number' => '4242 4242 4242 4242']);

        $retried->assertRedirect(route('shop.order', $order));
        $this->assertSame(OrderStatus::Paid, $order->fresh()->status);
    }

    public function test_a_card_number_is_required(): void
    {
        $shopper = $this->arriveAs($this->verifiedCustomer());
        $order = $this->unpaidOrderFor($shopper);

        $this->post(route('shop.order.pay', $order), [])->assertSessionHasErrors('card_number');
    }

    private function unpaidOrderFor(Customer $customer): Order
    {
        return $this->orderFor($customer, $this->listing($this->seller(), ['price_cents' => 24500]));
    }
}
