<?php

declare(strict_types=1);

namespace App\Actions\Fulfillment;

use App\Actions\Cart\AddToCart;
use App\Actions\Configurator\AddOptionValue;
use App\Actions\Configurator\AddUnit;
use App\Actions\Configurator\CreateOptionAxis;
use App\Actions\Configurator\CreateVariant;
use App\Actions\Configurator\GenerateVariants;
use App\Actions\Orders\FinalizeOrder;
use App\Actions\Orders\PlaceOrder;
use App\Domain\Auth\ActorType;
use App\Domain\Configurator\UnitState;
use App\Domain\DomainRuleViolation;
use App\Domain\Escrow\LedgerEntryType;
use App\Domain\Listings\ListingStatus;
use App\Domain\Orders\FulfillmentStatus;
use App\Domain\Orders\OrderStatus;
use App\Models\LedgerEntry;
use App\Models\Refund;
use Tests\CapturedStory;

it('declines a parcel the seller has not shipped', function (): void {
    $fulfillment = $this->paidFulfillmentFor($this->seller());

    $declined = app(DeclineFulfillment::class)($fulfillment, 'The kiln cracked the glaze.', $this->moment('2026-08-21 09:00:00'));

    expect($declined->status)->toBe(FulfillmentStatus::Declined);
});

it('refunds the whole fulfillment subtotal in the seller\'s name', function (): void {
    $seller = $this->seller();
    $fulfillment = $this->paidFulfillmentFor($seller, priceCents: 10000);

    app(DeclineFulfillment::class)($fulfillment, 'The kiln cracked the glaze.', $this->moment('2026-08-21 09:00:00'));

    $refund = Refund::sole();

    expect($refund->amount_cents)->toBe(10000)
        ->and($refund->reason)->toBe('The kiln cracked the glaze.')
        ->and($refund->issuer())->toBe(ActorType::Seller)
        ->and($refund->issued_by_id)->toBe($seller->id)
        ->and($refund->payment_id)->toBe($fulfillment->order->payments()->sole()->id)
        ->and($fulfillment->order->fresh()?->refunded_cents)->toBe(10000);
});

it('runs the net back out of escrow', function (): void {
    $fulfillment = $this->paidFulfillmentFor($this->seller(), priceCents: 10000);

    app(DeclineFulfillment::class)($fulfillment, 'Out of stock.', $this->moment('2026-08-21 09:00:00'));

    $entry = LedgerEntry::where('type', LedgerEntryType::Refunded)->sole();

    expect($entry->amount_cents)->toBe(-9000)
        ->and($entry->fulfillment_id)->toBe($fulfillment->id)
        ->and($fulfillment->seller->escrowBalance()->held->cents)->toBe(0)
        ->and($fulfillment->seller->escrowBalance()->available->cents)->toBe(0);
});

it('puts exactly the declined quantities back on the storefront', function (): void {
    $seller = $this->seller();
    $listing = $this->listing($seller, ['quantity' => 1, 'price_cents' => 10000]);
    $order = $this->orderFor($this->verifiedCustomer(), $listing);
    app(FinalizeOrder::class)($order, '4242424242424242', $this->moment('2026-08-20 10:00:00'));

    expect($listing->refresh()->status)->toBe(ListingStatus::Sold);

    app(DeclineFulfillment::class)($order->fulfillments()->sole(), 'Damaged.', $this->moment('2026-08-21 09:00:00'));

    expect($listing->refresh()->quantity)->toBe(1)
        ->and($listing->status)->toBe(ListingStatus::ForSale);
});

it('restores a configured lines variant quantity on decline', function (): void {
    $seller = $this->seller();
    $listing = $this->listing($seller);
    $axis = app(CreateOptionAxis::class)($listing, 'Size');
    app(AddOptionValue::class)($axis, 'M', 0, isDefault: true);
    app(GenerateVariants::class)($listing);
    $variant = $listing->variants()->sole();
    $variant->update(['quantity' => 3]);

    $customer = $this->verifiedCustomer();
    $cart = $this->cartFor($customer);
    app(AddToCart::class)($cart, $listing, 2, $this->moment('2026-08-20 08:00:00'), listingHasVariants: true, variant: $variant);
    $order = app(PlaceOrder::class)($cart, $this->purchaser($customer), $this->shippingAddress(), $this->moment('2026-08-20 09:00:00'));
    app(FinalizeOrder::class)($order, '4242424242424242', $this->moment('2026-08-20 10:00:00'));

    expect($variant->refresh()->quantity)->toBe(1);

    app(DeclineFulfillment::class)($order->fulfillments()->sole(), 'Damaged.', $this->moment('2026-08-21 09:00:00'));

    expect($variant->refresh()->quantity)->toBe(3);
});

it('restores a configured line’s serialized unit to available on decline', function (): void {
    $seller = $this->seller();
    $listing = $this->listing($seller);
    $variant = app(CreateVariant::class)($listing, [], isSerialized: true);
    $unit = app(AddUnit::class)($variant, '#1');

    $customer = $this->verifiedCustomer();
    $cart = $this->cartFor($customer);
    app(AddToCart::class)($cart, $listing, 1, $this->moment('2026-08-20 08:00:00'), listingHasVariants: true, variant: $variant, unitId: $unit->id);
    $order = app(PlaceOrder::class)($cart, $this->purchaser($customer), $this->shippingAddress(), $this->moment('2026-08-20 09:00:00'));
    app(FinalizeOrder::class)($order, '4242424242424242', $this->moment('2026-08-20 10:00:00'));

    expect($unit->refresh()->state)->toBe(UnitState::Sold);

    app(DeclineFulfillment::class)($order->fulfillments()->sole(), 'Damaged.', $this->moment('2026-08-21 09:00:00'));

    expect($unit->refresh()->state)->toBe(UnitState::Available);
});

it('leaves another seller\'s lines on the same order sold', function (): void {
    $order = $this->paidOrderWithTwoSellers();
    $declined = $order->fulfillments()->orderBy('id')->firstOrFail();
    $other = $order->fulfillments()->where('id', '!=', $declined->id)->sole();

    app(DeclineFulfillment::class)($declined, 'Damaged.', $this->moment('2026-08-21 09:00:00'));

    $otherItem = $order->items()->where('seller_id', $other->seller_id)->sole();

    expect($otherItem->listing->refresh()->status)->toBe(ListingStatus::Sold);
});

it('rolls the order up from the fulfillments still live', function (): void {
    $order = $this->paidOrderWithTwoSellers();
    $declined = $order->fulfillments()->orderBy('id')->firstOrFail();

    app(DeclineFulfillment::class)($declined, 'Damaged.', $this->moment('2026-08-21 09:00:00'));

    expect($order->fresh()?->status)->toBe(OrderStatus::Paid);
});

it('refunds the whole order once every fulfillment is settled', function (): void {
    $order = $this->paidOrderWithTwoSellers();

    foreach ($order->fulfillments()->orderBy('id')->get() as $fulfillment) {
        app(DeclineFulfillment::class)($fulfillment, 'Damaged.', $this->moment('2026-08-21 09:00:00'));
    }

    expect($order->fresh()?->status)->toBe(OrderStatus::Refunded)
        ->and($order->fresh()?->refunded_cents)->toBe(55000);
});

it('refuses a decline after the parcel shipped', function (): void {
    $fulfillment = $this->shippedFulfillmentFor($this->seller());

    expect(fn () => app(DeclineFulfillment::class)($fulfillment, 'Too late.', $this->moment('2026-08-22 09:00:00')))
        ->toThrow(DomainRuleViolation::class, 'shipped to declined');

    expect($fulfillment->fresh()?->status)->toBe(FulfillmentStatus::Shipped)
        ->and(Refund::count())->toBe(0);
});

it('refuses a second decline', function (): void {
    $fulfillment = $this->paidFulfillmentFor($this->seller());
    app(DeclineFulfillment::class)($fulfillment, 'Damaged.', $this->moment('2026-08-21 09:00:00'));

    expect(fn () => app(DeclineFulfillment::class)($fulfillment, 'Damaged again.', $this->moment('2026-08-21 10:00:00')))
        ->toThrow(DomainRuleViolation::class, 'declined to declined');

    expect(Refund::count())->toBe(1);
});

it('refuses to decline a fulfillment on an order nobody has paid for', function (): void {
    $order = $this->orderFor($this->verifiedCustomer(), $this->listing($this->seller()));
    $fulfillment = $order->fulfillments()->sole();

    expect(fn () => app(DeclineFulfillment::class)($fulfillment, 'Damaged.', $this->moment('2026-08-21 09:00:00')))
        ->toThrow(DomainRuleViolation::class, 'has nothing to refund');

    expect($fulfillment->fresh()?->status)->toBe(FulfillmentStatus::AwaitingShipment)
        ->and(Refund::count())->toBe(0)
        ->and(LedgerEntry::count())->toBe(0);
});

it('refuses to ship a parcel that was declined', function (): void {
    $fulfillment = $this->paidFulfillmentFor($this->seller());
    app(DeclineFulfillment::class)($fulfillment, 'Damaged.', $this->moment('2026-08-21 09:00:00'));

    expect(fn () => app(MarkShipped::class)($fulfillment->refresh(), 'Royal Mail', 'RM999', $this->moment('2026-08-22 09:00:00')))
        ->toThrow(DomainRuleViolation::class, 'declined to shipped');
});

it('tells the story of the decline and the refund it issued', function (): void {
    $fulfillment = $this->paidFulfillmentFor($this->seller());
    $log = CapturedStory::capture();

    app(DeclineFulfillment::class)($fulfillment, 'Damaged.', $this->moment('2026-08-21 09:00:00'));

    expect($log->values('phase', 'fulfillment.decline'))->toBe(['will', 'did'])
        ->and($log->line('refund.issue', 'did')['data'])->toMatchArray([
            'fulfillment_id' => $fulfillment->id,
            'amount_cents' => 10000,
            'reason' => 'Damaged.',
        ]);
});
