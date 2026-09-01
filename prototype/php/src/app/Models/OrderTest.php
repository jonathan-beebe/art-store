<?php

declare(strict_types=1);

namespace App\Models;

use App\Actions\Cart\AddToCart;
use App\Actions\Configurator\AddOptionValue;
use App\Actions\Configurator\CreateOptionAxis;
use App\Actions\Configurator\GenerateVariants;
use App\Actions\Orders\PlaceOrder;
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

it('blocks a line the placement plan finds unavailable', function (string $reasonKind, string $expectedTitle, UnavailableReason $expectedReason): void {
    $customer = $this->verifiedCustomer();

    if ($reasonKind === 'sold_out') {
        $listing = $this->listing($this->seller(), ['title' => 'Winter Elm', 'quantity' => 1]);
        $order = $this->orderFor($customer, $listing);
        $plan = $order->load('items.listing')->placementPlan();
    } elseif ($reasonKind === 'removed') {
        $listing = $this->listing($this->seller(), ['title' => 'Winter Elm', 'quantity' => 2]);
        $order = $this->orderFor($customer, $listing);
        ListingRemoval::factory()->create(['listing_id' => $listing->id]);
        $plan = $order->load('items.listing')->placementPlan();
    } else {
        $listing = $this->listing($this->seller(), ['title' => 'Line Art Cat Tee']);
        $axis = app(CreateOptionAxis::class)($listing, 'Size');
        app(AddOptionValue::class)($axis, 'M', 0, isDefault: true);
        app(GenerateVariants::class)($listing);
        $variant = $listing->variants()->sole();
        $variant->update(['quantity' => 1]);

        $cart = $this->cartFor($customer);
        app(AddToCart::class)($cart, $listing, 1, $this->moment('2026-08-20 08:00:00'), listingHasVariants: true, variant: $variant);
        $order = app(PlaceOrder::class)($cart, $this->purchaser($customer), $this->shippingAddress(), $this->moment('2026-08-20 09:00:00'));

        $variant->update(['quantity' => 0]);

        $plan = $order->load('items.listing', 'items.variant')->placementPlan();
    }

    expect($plan->isPlaceable())->toBeFalse()
        ->and($plan->blocked[0]->title)->toBe($expectedTitle)
        ->and($plan->blocked[0]->reason)->toBe($expectedReason);
})->with([
    'sold out by its own placement' => ['sold_out', 'Winter Elm', UnavailableReason::SoldOut],
    'removed even while for sale' => ['removed', 'Winter Elm', UnavailableReason::Removed],
    'sold out via its variant rather than the listing quantity' => ['variant_sold_out', 'Line Art Cat Tee', UnavailableReason::SoldOut],
]);

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
