<?php

declare(strict_types=1);

namespace App\Actions\Orders;

use App\Domain\Listings\ListingStatus;
use App\Domain\Orders\OrderStatus;
use Tests\CapturedStory;

it('cancels a guest order left unverified past the cutoff', function (): void {
    $order = $this->orderFor($this->anonymousCustomer(), $this->listing($this->seller()));
    $order->update(['placed_at' => $this->moment('2026-08-20 09:00:00')]);

    $cancelled = app(SweepStaleOrders::class)($this->moment('2026-08-21 10:00:00'), 24);

    expect($cancelled)->toHaveCount(1)
        ->and($order->fresh()?->status)->toBe(OrderStatus::Cancelled);
});

it('hands the stock of a swept order back to the storefront', function (): void {
    $listing = $this->listing($this->seller(), ['quantity' => 1]);
    $order = $this->orderFor($this->anonymousCustomer(), $listing);
    $order->update(['placed_at' => $this->moment('2026-08-20 09:00:00')]);

    app(SweepStaleOrders::class)($this->moment('2026-08-21 10:00:00'), 24);

    expect($listing->refresh()->quantity)->toBe(1)
        ->and($listing->status)->toBe(ListingStatus::ForSale);
});

it('sweeps an order placed exactly at the cutoff instant', function (): void {
    $order = $this->orderFor($this->anonymousCustomer(), $this->listing($this->seller()));
    $order->update(['placed_at' => $this->moment('2026-08-20 10:00:00')]);

    $cancelled = app(SweepStaleOrders::class)($this->moment('2026-08-21 10:00:00'), 24);

    expect($cancelled)->toHaveCount(1)
        ->and($order->fresh()?->status)->toBe(OrderStatus::Cancelled);
});

it('leaves an order younger than the cutoff alone', function (): void {
    $order = $this->orderFor($this->anonymousCustomer(), $this->listing($this->seller()));
    $order->update(['placed_at' => $this->moment('2026-08-21 09:00:00')]);

    expect(app(SweepStaleOrders::class)($this->moment('2026-08-21 10:00:00'), 24))->toBe([])
        ->and($order->fresh()?->status)->toBe(OrderStatus::PendingVerification);
});

it('never touches an order that is only awaiting payment', function (): void {
    $order = $this->orderFor($this->verifiedCustomer(), $this->listing($this->seller()));
    $order->update(['placed_at' => $this->moment('2026-08-01 09:00:00')]);

    expect(app(SweepStaleOrders::class)($this->moment('2026-08-21 10:00:00'), 24))->toBe([])
        ->and($order->fresh()?->status)->toBe(OrderStatus::AwaitingPayment);
});

it('finds nothing left on a second run', function (): void {
    $order = $this->orderFor($this->anonymousCustomer(), $this->listing($this->seller()));
    $order->update(['placed_at' => $this->moment('2026-08-20 09:00:00')]);

    app(SweepStaleOrders::class)($this->moment('2026-08-21 10:00:00'), 24);

    expect(app(SweepStaleOrders::class)($this->moment('2026-08-21 11:00:00'), 24))->toBe([]);
});

it('tells the sweep as the system, one line per order', function (): void {
    $first = $this->orderFor($this->anonymousCustomer(), $this->listing($this->seller('Blue Kiln Studio')));
    $second = $this->orderFor($this->anonymousCustomer(), $this->listing($this->seller('Rye Press')));
    $first->update(['placed_at' => $this->moment('2026-08-20 08:00:00')]);
    $second->update(['placed_at' => $this->moment('2026-08-20 09:00:00')]);
    $log = CapturedStory::capture();

    app(SweepStaleOrders::class)($this->moment('2026-08-21 10:00:00'), 24);

    expect($log->values('phase', 'order.sweep'))->toBe(['will', 'doing', 'doing', 'did'])
        ->and($log->line('order.sweep', 'did')['data'])->toMatchArray(['cancelled_count' => 2])
        ->and($log->values('actor_type', 'order.cancel'))->toBe(['system', 'system', 'system', 'system']);
});
