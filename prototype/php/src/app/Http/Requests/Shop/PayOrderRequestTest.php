<?php

declare(strict_types=1);

namespace App\Http\Requests\Shop;

use App\Domain\Orders\OrderStatus;
use App\Models\Customer;
use App\Models\Order;

$unpaidOrderFor = function (Customer $customer): Order {
    return test()->orderFor($customer, test()->listing(test()->seller(), ['price_cents' => 24500]));
};

it('refuses a payment the card field cannot carry', function (array $submitted, string $field) use ($unpaidOrderFor): void {
    $shopper = $this->arriveAs($this->verifiedCustomer());
    $order = $unpaidOrderFor($shopper);

    $response = $this->post(route('shop.order.pay', $order), $submitted);

    $response->assertSessionHasErrors($field);
    expect($order->fresh()->status)->toBe(OrderStatus::AwaitingPayment);
})->with([
    'no card at all' => [[], 'card_number'],
    'an empty card field' => [['card_number' => ''], 'card_number'],
    'a card longer than the field holds' => [['card_number' => str_repeat('4', 33)], 'card_number'],
]);

it('answers another customers order before it reads the card field', function () use ($unpaidOrderFor): void {
    $order = $unpaidOrderFor($this->verifiedCustomer());
    $this->arriveAs($this->verifiedCustomer());

    $response = $this->post(route('shop.order.pay', $order), []);

    $response->assertNotFound();
    $response->assertSessionHasNoErrors();
});

it('reads the card number the shopper typed', function (): void {
    $request = PayOrderRequest::create('/orders/1/pay', 'POST', ['card_number' => '4242 4242 4242 4242']);

    expect($request->cardNumber())->toBe('4242 4242 4242 4242');
});
