<?php

declare(strict_types=1);

namespace App\Actions\Orders;

use App\Actions\Cart\AddToCart;
use App\Actions\Configurator\AddOptionValue;
use App\Actions\Configurator\AddUnit;
use App\Actions\Configurator\CreateOptionAxis;
use App\Actions\Configurator\CreateVariant;
use App\Actions\Configurator\GenerateVariants;
use App\Domain\Configurator\UnitState;
use App\Domain\DomainRuleViolation;
use App\Domain\Listings\ListingStatus;
use App\Domain\Orders\OrderStatus;
use App\Events\OrderCancelled;
use Illuminate\Support\Facades\Event;
use Tests\CapturedStory;

it('cancels an order that is still waiting for a card', function (): void {
    $order = $this->orderFor($this->verifiedCustomer(), $this->listing($this->seller()));

    $cancelled = app(CancelOrder::class)($order, $this->moment('2026-08-21 09:00:00'));

    expect($cancelled->status)->toBe(OrderStatus::Cancelled)
        ->and($order->fresh()?->status)->toBe(OrderStatus::Cancelled);
});

it('cancels a guest order that was never verified', function (): void {
    $order = $this->orderFor($this->anonymousCustomer(), $this->listing($this->seller()));

    expect($order->status)->toBe(OrderStatus::PendingVerification)
        ->and(app(CancelOrder::class)($order, $this->moment('2026-08-21 09:00:00'))->status)
        ->toBe(OrderStatus::Cancelled);
});

it('puts the stock it was holding back on the storefront', function (): void {
    $listing = $this->listing($this->seller(), ['quantity' => 1]);
    $order = $this->orderFor($this->verifiedCustomer(), $listing);

    expect($listing->refresh()->status)->toBe(ListingStatus::Sold);

    app(CancelOrder::class)($order, $this->moment('2026-08-21 09:00:00'));

    expect($listing->refresh()->quantity)->toBe(1)
        ->and($listing->status)->toBe(ListingStatus::ForSale);
});

it('DSGN-003 leaves a made-to-order listings quantity null on cancel', function (): void {
    $listing = $this->listing($this->seller(), ['quantity' => null]);
    $order = $this->orderFor($this->verifiedCustomer(), $listing);

    expect($listing->refresh()->status)->toBe(ListingStatus::ForSale);

    app(CancelOrder::class)($order, $this->moment('2026-08-21 09:00:00'));

    expect($listing->refresh()->quantity)->toBeNull()
        ->and($listing->status)->toBe(ListingStatus::ForSale);
});

it('leaves the stock alone when a declined card already handed it back', function (): void {
    $listing = $this->listing($this->seller(), ['quantity' => 1]);
    $order = $this->orderFor($this->verifiedCustomer(), $listing);
    app(FinalizeOrder::class)($order, '4000000000000002', $this->moment('2026-08-20 10:00:00'));

    expect($order->refresh()->status)->toBe(OrderStatus::PaymentFailed)
        ->and($listing->refresh()->quantity)->toBe(1);

    app(CancelOrder::class)($order, $this->moment('2026-08-21 09:00:00'));

    expect($listing->refresh()->quantity)->toBe(1)
        ->and($listing->status)->toBe(ListingStatus::ForSale);
});

it('restores a configured lines variant quantity and a serialized lines unit on cancel', function (): void {
    $customer = $this->verifiedCustomer();
    $listing = $this->listing($this->seller());
    $axis = app(CreateOptionAxis::class)($listing, 'Size');
    app(AddOptionValue::class)($axis, 'M', 0, isDefault: true);
    app(GenerateVariants::class)($listing);
    $variant = $listing->variants()->sole();
    $variant->update(['quantity' => 3]);

    $serializedListing = $this->listing($this->seller());
    $serializedVariant = app(CreateVariant::class)($serializedListing, [], isSerialized: true);
    $unit = app(AddUnit::class)($serializedVariant, '#1');

    $cart = $this->cartFor($customer);
    $addToCart = app(AddToCart::class);
    $addToCart($cart, $listing, 2, $this->moment('2026-08-20 08:00:00'), listingHasVariants: true, variant: $variant);
    $addToCart($cart, $serializedListing, 1, $this->moment('2026-08-20 08:00:00'), listingHasVariants: true, variant: $serializedVariant, unitId: $unit->id);

    $order = app(PlaceOrder::class)($cart, $this->purchaser($customer), $this->shippingAddress(), $this->moment('2026-08-20 09:00:00'));

    expect($variant->refresh()->quantity)->toBe(1)
        ->and($unit->refresh()->state)->toBe(UnitState::Sold);

    app(CancelOrder::class)($order, $this->moment('2026-08-21 09:00:00'));

    expect($variant->refresh()->quantity)->toBe(3)
        ->and($unit->refresh()->state)->toBe(UnitState::Available);
});

it('refuses to cancel an order that has been paid', function (): void {
    $order = $this->paidOrderWithTwoSellers();

    expect(fn () => app(CancelOrder::class)($order, $this->moment('2026-08-21 09:00:00')))
        ->toThrow(DomainRuleViolation::class, 'paid to cancelled');

    expect($order->fresh()?->status)->toBe(OrderStatus::Paid);
});

it('refuses to cancel an order that is already cancelled', function (): void {
    $order = $this->orderFor($this->verifiedCustomer(), $this->listing($this->seller()));
    app(CancelOrder::class)($order, $this->moment('2026-08-21 09:00:00'));

    expect(fn () => app(CancelOrder::class)($order, $this->moment('2026-08-21 10:00:00')))
        ->toThrow(DomainRuleViolation::class, 'cancelled to cancelled');
});

it('raises the cancellation once the stock is back', function (): void {
    $order = $this->orderFor($this->verifiedCustomer(), $this->listing($this->seller()));
    Event::fake([OrderCancelled::class]);

    app(CancelOrder::class)($order, $this->moment('2026-08-21 09:00:00'));

    Event::assertDispatched(
        OrderCancelled::class,
        fn (OrderCancelled $event): bool => $event->order->is($order)
            && $event->cancelledAt->format('Y-m-d H:i:s') === '2026-08-21 09:00:00',
    );
});

it('tells the story of the cancellation', function (): void {
    $order = $this->orderFor($this->verifiedCustomer(), $this->listing($this->seller()));
    $log = CapturedStory::capture();

    app(CancelOrder::class)($order, $this->moment('2026-08-21 09:00:00'));

    expect($log->values('phase', 'order.cancel'))->toBe(['will', 'did'])
        ->and($log->line('order.cancel', 'did')['data'])->toMatchArray([
            'order_id' => $order->id,
            'status_to' => 'cancelled',
        ]);
});

it('tells the story of a refusal without changing anything', function (): void {
    $order = $this->paidOrderWithTwoSellers();
    $log = CapturedStory::capture();

    expect(fn () => app(CancelOrder::class)($order, $this->moment('2026-08-21 09:00:00')))
        ->toThrow(DomainRuleViolation::class);

    expect($log->values('phase', 'order.cancel'))->toBe(['will', 'refused']);
});
