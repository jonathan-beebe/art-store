<?php

declare(strict_types=1);

namespace App\Seller;

use App\Actions\Cart\AddToCart;
use App\Actions\Fulfillment\DeclineFulfillment;
use App\Actions\Orders\FinalizeOrder;
use App\Actions\Orders\PlaceOrder;
use App\Domain\Seller\ActivityKind;
use App\Domain\Seller\FeedEvent;
use App\Domain\Seller\FeedIcon;
use App\Models\Customer;

it('a paid fulfillment produces the order placed, the approved payment, and the held movement, in that order', function (): void {
    $seller = $this->seller('The Burrow Craftworks');
    $customer = Customer::factory()->create(['name' => 'Harry Potter']);
    $listing = $this->listing($seller, ['title' => 'The Burrow at Dusk', 'price_cents' => 6000, 'quantity' => 5]);

    $cart = $this->cartFor($customer);
    app(AddToCart::class)($cart, $listing, 2, $this->moment('2026-08-20 08:00:00'));
    $order = app(PlaceOrder::class)($cart, $this->purchaser($customer), $this->shippingAddress(), $this->moment('2026-08-20 09:00:00'));
    app(FinalizeOrder::class)($order, '4242424242424242', $this->moment('2026-08-20 10:00:00'));
    $fulfillment = $order->fulfillments()->sole();
    $payment = $fulfillment->order->payments()->sole();

    $events = (new OrderSource)->events(FeedScope::forFulfillment($fulfillment));

    expect($events)->toHaveCount(3);

    foreach ($events as $event) {
        expect($event->kind)->toBe(ActivityKind::Order);
    }

    expect($events[0]->text)->toBe("placed order {$order->id} · The Burrow at Dusk ×2 · ".$fulfillment->subtotal()->format())
        ->and($events[0]->actor)->toBe('Harry Potter')
        ->and($events[0]->link)->toBe(route('seller.orders.show', $fulfillment->id));

    expect($events[1]->text)->toBe('approved on card ending '.$payment->card_last_four.' · '.$payment->amount()->format())
        ->and($events[1]->icon)->toBe(FeedIcon::Card);

    expect($events[2]->text)->toBe('held in escrow after the platform fee')
        ->and($events[2]->icon)->toBe(FeedIcon::Cash);
});

it('a declined payment produces a row carrying the decline reason', function (): void {
    $seller = $this->seller('The Burrow Craftworks');
    $customer = Customer::factory()->create(['name' => 'Hermione Granger']);
    $listing = $this->listing($seller, ['title' => 'Nine Owls', 'price_cents' => 6000]);
    $order = $this->orderFor($customer, $listing);
    app(FinalizeOrder::class)($order, '4000 0000 0000 0002', $this->moment('2026-08-20 10:00:00'));
    $fulfillment = $order->fulfillments()->sole();

    $events = (new OrderSource)->events(FeedScope::forFulfillment($fulfillment));

    expect($events)->toHaveCount(2)
        ->and($events[1]->text)->toBe('declined on card ending 0002 — Your card was declined.')
        ->and($events[1]->kind)->toBe(ActivityKind::Order);
});

it('a delivered fulfillment adds the released movement', function (): void {
    $seller = $this->seller('The Burrow Craftworks');
    $customer = Customer::factory()->create(['name' => 'Ginny Weasley']);
    $fulfillment = $this->deliveredFulfillmentFor($seller, $customer, 8000);

    $events = (new OrderSource)->events(FeedScope::forFulfillment($fulfillment));
    $released = array_values(array_filter($events, fn (FeedEvent $event): bool => $event->text === 'released to your available balance'));

    expect($released)->toHaveCount(1)
        ->and($released[0]->kind)->toBe(ActivityKind::Order);
});

it('a declined fulfillment\'s refunded movement carries the refund reason as its quote', function (): void {
    $seller = $this->seller('The Burrow Craftworks');
    $fulfillment = $this->paidFulfillmentFor($seller);
    app(DeclineFulfillment::class)($fulfillment, 'The kiln cracked the glaze.', $this->moment('2026-08-21 09:00:00'));

    $events = (new OrderSource)->events(FeedScope::forFulfillment($fulfillment->refresh()));
    $refunded = array_values(array_filter($events, fn (FeedEvent $event): bool => $event->text === 'returned to the buyer'));

    expect($refunded)->toHaveCount(1)
        ->and($refunded[0]->quote)->toBe('The kiln cracked the glaze.')
        ->and($refunded[0]->kind)->toBe(ActivityKind::Order);
});

it('every row is ActivityKind::Order', function (): void {
    $seller = $this->seller('The Burrow Craftworks');
    $customer = Customer::factory()->create(['name' => 'Cho Chang']);
    $paid = $this->paidFulfillmentFor($seller, $customer, 5000);
    $declined = $this->paidFulfillmentFor($seller, $customer, 3000);
    app(DeclineFulfillment::class)($declined, 'Damaged.', $this->moment('2026-08-21 09:00:00'));

    $events = (new OrderSource)->events(FeedScope::forCustomer($seller, $customer));

    expect($events)->not->toBeEmpty();

    foreach ($events as $event) {
        expect($event->kind)->toBe(ActivityKind::Order);
    }
});
