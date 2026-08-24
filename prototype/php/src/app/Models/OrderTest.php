<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Orders\OrderStatus;
use App\Domain\Orders\UnavailableReason;

it('reads its totals as money', function (): void {
    $order = $this->orderFor($this->verifiedCustomer(), $this->listing($this->seller(), ['price_cents' => 45000]));

    expect($order->subtotal())->toBeMoney(45000)
        ->and($order->total())->toBeMoney(45000);
});

it('reads the latest of its payment attempts', function (): void {
    $order = $this->orderFor($this->verifiedCustomer(), $this->listing($this->seller()));
    Payment::factory()->declined()->create([
        'order_id' => $order->id,
        'amount_cents' => $order->total_cents,
        'processed_at' => $this->moment('2026-08-20 10:00:00'),
    ]);
    $retry = Payment::factory()->approved()->create([
        'order_id' => $order->id,
        'amount_cents' => $order->total_cents,
        'processed_at' => $this->moment('2026-08-20 10:05:00'),
    ]);

    expect($order->latestPayment()->sole()->is($retry))->toBeTrue();
});

it('has no latest payment before the first attempt', function (): void {
    $order = $this->orderFor($this->verifiedCustomer(), $this->listing($this->seller()));

    expect($order->latestPayment)->toBeNull();
});

it('reads the items, fulfillments, and customer behind it', function (): void {
    $customer = $this->verifiedCustomer();
    $order = $this->orderFor($customer, $this->listing($this->seller()));

    expect($order->items()->count())->toBe(1)
        ->and($order->fulfillments()->count())->toBe(1)
        ->and($order->customer->is($customer))->toBeTrue();
});

it('plans placement from its items against the listings behind them', function (): void {
    $listing = $this->listing($this->seller(), ['title' => 'Winter Elm', 'quantity' => 1]);
    $order = $this->orderFor($this->verifiedCustomer(), $listing);

    $plan = $order->load('items.listing')->placementPlan();

    expect($plan->isPlaceable())->toBeFalse()
        ->and($plan->blocked[0]->title)->toBe('Winter Elm')
        ->and($plan->blocked[0]->reason)->toBe(UnavailableReason::SoldOut);
});

it('blocks a line whose listing carries an active removal, even while for sale', function (): void {
    $listing = $this->listing($this->seller(), ['title' => 'Winter Elm', 'quantity' => 2]);
    $order = $this->orderFor($this->verifiedCustomer(), $listing);
    ListingRemoval::factory()->create(['listing_id' => $listing->id]);

    $plan = $order->load('items.listing')->placementPlan();

    expect($plan->isPlaceable())->toBeFalse()
        ->and($plan->blocked[0]->title)->toBe('Winter Elm')
        ->and($plan->blocked[0]->reason)->toBe(UnavailableReason::Removed);
});

it('counts every status the table holds, in one row each', function (): void {
    $this->orderFor($this->verifiedCustomer(), $this->listing($this->seller()));
    $this->orderFor($this->verifiedCustomer(), $this->listing($this->seller()));

    $counts = [];
    foreach (Order::query()->countedByStatus()->get() as $row) {
        $counts[$row->status->value] = $row->tally;
    }

    expect($counts)->toBe([OrderStatus::AwaitingPayment->value => 2]);
});

it('counts every status across the whole platform', function (): void {
    $this->orderFor($this->verifiedCustomer(), $this->listing($this->seller()));

    expect(Order::platformCountsByStatus())->toBe([OrderStatus::AwaitingPayment->value => 1]);
});
