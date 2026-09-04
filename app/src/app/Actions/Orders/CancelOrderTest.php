<?php

declare(strict_types=1);

namespace App\Actions\Orders;

use App\Actions\Cart\AddToCart;
use App\Actions\Configurator\AddOptionValue;
use App\Actions\Configurator\AddUnit;
use App\Actions\Configurator\CreateOptionAxis;
use App\Actions\Configurator\CreateVariant;
use App\Actions\Configurator\GenerateVariants;
use App\Analytics\Analytics;
use App\Domain\Configurator\UnitState;
use App\Domain\DomainRuleViolation;
use App\Domain\Listings\ListingStatus;
use App\Domain\Orders\OrderStatus;
use Illuminate\Support\Facades\DB;
use Tests\CapturedStory;

it('cancels an order regardless of its starting status', function (OrderStatus $startingStatus): void {
    $customer = $startingStatus === OrderStatus::PendingVerification ? $this->anonymousCustomer() : $this->verifiedCustomer();
    $order = $this->orderFor($customer, $this->listing($this->seller()));

    expect($order->status)->toBe($startingStatus);

    $cancelled = app(CancelOrder::class)($order, $this->moment('2026-08-21 09:00:00'));

    expect($cancelled->status)->toBe(OrderStatus::Cancelled)
        ->and($order->fresh()?->status)->toBe(OrderStatus::Cancelled);
})->with([
    'a card still waiting to be charged' => [OrderStatus::AwaitingPayment],
    'a guest order that was never verified' => [OrderStatus::PendingVerification],
]);

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

it('restocks both fulfillments of a multi-seller order on cancel', function (): void {
    $customer = $this->verifiedCustomer();
    $first = $this->listing($this->seller('Blue Kiln Studio'), ['quantity' => 1]);
    $second = $this->listing($this->seller('Rye Press'), ['quantity' => 1]);
    $order = $this->orderFor($customer, $first, $second);

    expect($first->refresh()->status)->toBe(ListingStatus::Sold)
        ->and($second->refresh()->status)->toBe(ListingStatus::Sold);

    app(CancelOrder::class)($order, $this->moment('2026-08-21 09:00:00'));

    expect($first->refresh()->quantity)->toBe(1)
        ->and($first->status)->toBe(ListingStatus::ForSale)
        ->and($second->refresh()->quantity)->toBe(1)
        ->and($second->status)->toBe(ListingStatus::ForSale);
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

it('records an order.cancel event carrying the order\'s listings', function (): void {
    $customer = $this->verifiedCustomer();
    $listing = $this->listing($this->seller(), ['price_cents' => 45000]);
    $order = $this->orderFor($customer, $listing);
    $now = $this->moment('2026-08-21 09:00:00');

    app(CancelOrder::class)($order, $now);
    app(Analytics::class)->flush();

    $event = DB::connection('analytics')->table('analytics_events')->where('name', 'order.cancel')->sole();
    /** @var string $eventData */
    $eventData = $event->data;
    /** @var array<string, mixed> $data */
    $data = json_decode($eventData, true);

    expect($event->subject_type)->toBe('order')
        ->and($event->subject_id)->toBe($order->id)
        ->and($event->actor_id)->toBe($customer->id)
        ->and($event->occurred_at)->toBe('2026-08-21 09:00:00')
        ->and($data['listing_ids'])->toBe([$listing->id]);
});

it('records no order.cancel event when the cancellation is refused', function (): void {
    $order = $this->paidOrderWithTwoSellers();

    expect(fn () => app(CancelOrder::class)($order, $this->moment('2026-08-21 09:00:00')))
        ->toThrow(DomainRuleViolation::class);

    app(Analytics::class)->flush();

    expect(DB::connection('analytics')->table('analytics_events')->where('name', 'order.cancel')->exists())->toBeFalse();
});
