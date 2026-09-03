<?php

declare(strict_types=1);

namespace App\Actions\Orders;

use App\Actions\Cart\AddToCart;
use App\Actions\Configurator\AddOptionValue;
use App\Actions\Configurator\CreateOptionAxis;
use App\Actions\Configurator\GenerateVariants;
use App\Actions\Fulfillment\DeclineFulfillment;
use App\Analytics\Analytics;
use App\Domain\DomainRuleViolation;
use App\Domain\Escrow\LedgerEntryType;
use App\Domain\Listings\ListingStatus;
use App\Domain\Orders\OrderPlacementRefused;
use App\Domain\Orders\OrderStatus;
use App\Domain\Orders\UnavailableReason;
use App\Domain\Payments\DeclineReason;
use App\Domain\Payments\PaymentStatus;
use App\Models\CustomerBlock;
use App\Models\LedgerEntry;
use App\Models\Listing;
use App\Notifications\ItemSold;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use RuntimeException;

it('pays the order with an approved card', function (): void {
    $order = $this->orderFor($this->verifiedCustomer(), $this->listing($this->seller(), ['price_cents' => 45000]));

    $order = app(FinalizeOrder::class)($order, '4242 4242 4242 4242', $this->moment('2026-08-20 10:00:00'));

    expect($order->status)->toBe(OrderStatus::Paid)
        ->and($order->finalized_at?->format('Y-m-d H:i:s'))->toBe('2026-08-20 10:00:00');
});

it('records the payment for an approved card', function (): void {
    $order = $this->orderFor($this->verifiedCustomer(), $this->listing($this->seller(), ['price_cents' => 45000]));

    app(FinalizeOrder::class)($order, '4242 4242 4242 4242', $this->moment('2026-08-20 10:00:00'));

    $payment = $order->payments()->sole();

    expect($payment->status)->toBe(PaymentStatus::Approved)
        ->and($payment->amount_cents)->toBe(45000)
        ->and($payment->card_last_four)->toBe('4242')
        ->and($payment->decline_reason)->toBeNull();
});

it('holds the seller net in escrow for a paid order', function (): void {
    $seller = $this->seller();
    $order = $this->orderFor($this->verifiedCustomer(), $this->listing($seller, ['price_cents' => 45000]));

    app(FinalizeOrder::class)($order, '4242 4242 4242 4242', $this->moment('2026-08-20 10:00:00'));

    $entry = LedgerEntry::query()->sole();

    expect($entry->type)->toBe(LedgerEntryType::Held)
        ->and($entry->amount_cents)->toBe(40500)
        ->and($entry->seller_id)->toBe($seller->id)
        ->and($entry->fulfillment_id)->toBe($order->fulfillments()->sole()->id);
});

it('holds one amount per seller on a paid order', function (): void {
    $order = $this->orderFor(
        $this->verifiedCustomer(),
        $this->listing($this->seller('Blue Kiln Studio'), ['price_cents' => 45000]),
        $this->listing($this->seller('Rye Press'), ['price_cents' => 10000]),
    );

    app(FinalizeOrder::class)($order, '4242 4242 4242 4242', $this->moment('2026-08-20 10:00:00'));

    expect(LedgerEntry::query()->orderBy('amount_cents')->pluck('amount_cents')->all())->toBe([9000, 40500]);
});

it('tells each seller their item sold on a paid order', function (): void {
    Notification::fake();
    $seller = $this->seller();
    $order = $this->orderFor($this->verifiedCustomer(), $this->listing($seller, ['price_cents' => 45000]));

    app(FinalizeOrder::class)($order, '4242 4242 4242 4242', $this->moment('2026-08-20 10:00:00'));

    Notification::assertSentTo(
        $seller,
        ItemSold::class,
        fn (ItemSold $notification): bool => str_contains($notification->toArray($seller)['body'], '$405.00'),
    );
});

it('fails the payment for a declined card', function (): void {
    $order = $this->orderFor($this->verifiedCustomer(), $this->listing($this->seller(), ['price_cents' => 45000]));

    $order = app(FinalizeOrder::class)($order, '4000 0000 0000 0002', $this->moment('2026-08-20 10:00:00'));

    expect($order->status)->toBe(OrderStatus::PaymentFailed)
        ->and($order->finalized_at)->toBeNull()
        ->and($order->payments()->sole()->decline_reason)->toBe(DeclineReason::GenericDecline);
});

it('records an order.pay event carrying the total and the order\'s listings', function (): void {
    $customer = $this->verifiedCustomer();
    $listing = $this->listing($this->seller(), ['price_cents' => 45000]);
    $order = $this->orderFor($customer, $listing);
    $now = $this->moment('2026-08-20 10:00:00');

    app(FinalizeOrder::class)($order, '4242 4242 4242 4242', $now);
    app(Analytics::class)->flush();

    $event = DB::connection('analytics')->table('analytics_events')->where('name', 'order.pay')->sole();
    /** @var string $eventData */
    $eventData = $event->data;
    /** @var array<string, mixed> $data */
    $data = json_decode($eventData, true);

    expect($event->subject_type)->toBe('order')
        ->and($event->subject_id)->toBe($order->id)
        ->and($event->actor_id)->toBe($customer->id)
        ->and($event->occurred_at)->toBe('2026-08-20 10:00:00')
        ->and($data['listing_ids'])->toBe([$listing->id])
        ->and($data['total_cents'])->toBe(45000);
});

it('records no order.pay event for a declined card', function (): void {
    $order = $this->orderFor($this->verifiedCustomer(), $this->listing($this->seller(), ['price_cents' => 45000]));

    app(FinalizeOrder::class)($order, '4000 0000 0000 0002', $this->moment('2026-08-20 10:00:00'));
    app(Analytics::class)->flush();

    expect(DB::connection('analytics')->table('analytics_events')->where('name', 'order.pay')->exists())->toBeFalse();
});

it('puts the stock back on the storefront for a declined card', function (): void {
    $listing = $this->listing($this->seller(), ['price_cents' => 45000, 'quantity' => 1]);
    $order = $this->orderFor($this->verifiedCustomer(), $listing);

    app(FinalizeOrder::class)($order, '4000 0000 0000 0002', $this->moment('2026-08-20 10:00:00'));

    expect($listing->refresh()->quantity)->toBe(1)
        ->and($listing->status)->toBe(ListingStatus::ForSale);
});

it('holds nothing and tells nobody for a declined card', function (): void {
    Notification::fake();
    $order = $this->orderFor($this->verifiedCustomer(), $this->listing($this->seller(), ['price_cents' => 45000]));

    app(FinalizeOrder::class)($order, '4000 0000 0000 0002', $this->moment('2026-08-20 10:00:00'));

    expect(LedgerEntry::query()->count())->toBe(0);
    Notification::assertNothingSent();
});

it('pays the order and takes the stock again on a retry with a good card', function (): void {
    $listing = $this->listing($this->seller(), ['price_cents' => 45000, 'quantity' => 1]);
    $order = $this->orderFor($this->verifiedCustomer(), $listing);
    $finalizeOrder = app(FinalizeOrder::class);
    $finalizeOrder($order, '4000 0000 0000 0002', $this->moment('2026-08-20 10:00:00'));

    $order = $finalizeOrder($order, '4242 4242 4242 4242', $this->moment('2026-08-20 10:05:00'));

    expect($order->status)->toBe(OrderStatus::Paid)
        ->and($order->payments()->count())->toBe(2);
    expect($listing->refresh()->quantity)->toBe(0)
        ->and($listing->status)->toBe(ListingStatus::Sold);
    expect(LedgerEntry::query()->sole()->amount_cents)->toBe(40500);
});

it('takes a configured lines variant quantity again on a retry with a good card', function (): void {
    $listing = $this->listing($this->seller());
    $axis = app(CreateOptionAxis::class)($listing, 'Size');
    app(AddOptionValue::class)($axis, 'M', 0, isDefault: true);
    app(GenerateVariants::class)($listing);
    $variant = $listing->variants()->sole();
    $variant->update(['quantity' => 1]);

    $customer = $this->verifiedCustomer();
    $cart = $this->cartFor($customer);
    app(AddToCart::class)($cart, $listing, 1, $this->moment('2026-08-20 08:00:00'), listingHasVariants: true, variant: $variant);
    $order = app(PlaceOrder::class)($cart, $this->purchaser($customer), $this->shippingAddress(), $this->moment('2026-08-20 09:00:00'));
    $finalizeOrder = app(FinalizeOrder::class);
    $finalizeOrder($order, '4000 0000 0000 0002', $this->moment('2026-08-20 10:00:00'));

    expect($variant->refresh()->quantity)->toBe(1);

    $order = $finalizeOrder($order, '4242 4242 4242 4242', $this->moment('2026-08-20 10:05:00'));

    expect($order->status)->toBe(OrderStatus::Paid)
        ->and($variant->refresh()->quantity)->toBe(0);
});

it('judges the retry against the rows it locked, not what the caller loaded before', function (): void {
    $listing = $this->listing($this->seller(), ['title' => 'Winter Elm', 'price_cents' => 45000, 'quantity' => 1]);
    $order = $this->orderFor($this->verifiedCustomer(), $listing);
    $finalizeOrder = app(FinalizeOrder::class);
    $finalizeOrder($order, '4000 0000 0000 0002', $this->moment('2026-08-20 10:00:00'));
    // What the pay page had read after the decline handed the stock back.
    $order->load('items.listing');
    Listing::whereKey($listing->id)->update(['quantity' => 0, 'status' => ListingStatus::Sold]);

    $retry = fn () => $finalizeOrder($order, '4242 4242 4242 4242', $this->moment('2026-08-20 10:05:00'));

    expect($retry)->toThrow(OrderPlacementRefused::class, '“Winter Elm” is no longer available to buy.')
        ->and($order->refresh()->payments()->count())->toBe(1);
});

it('refuses a retry when the listing sold to someone else while the card sat declined', function (): void {
    $listing = $this->listing($this->seller(), ['title' => 'Winter Elm', 'price_cents' => 45000, 'quantity' => 1]);
    $order = $this->orderFor($this->verifiedCustomer(), $listing);
    $finalizeOrder = app(FinalizeOrder::class);
    $finalizeOrder($order, '4000 0000 0000 0002', $this->moment('2026-08-20 10:00:00'));

    $this->orderFor($this->verifiedCustomer(), $listing->refresh());

    $retry = fn () => $finalizeOrder($order, '4242 4242 4242 4242', $this->moment('2026-08-20 10:05:00'));

    try {
        $retry();

        throw new RuntimeException('Expected the retry to be refused.');
    } catch (OrderPlacementRefused $refusal) {
        expect($refusal->blocked[0]->title)->toBe('Winter Elm')
            ->and($refusal->blocked[0]->reason)->toBe(UnavailableReason::SoldOut);
    }

    expect($order->refresh()->status)->toBe(OrderStatus::PaymentFailed)
        ->and($order->payments()->count())->toBe(1)
        ->and($listing->refresh()->status)->toBe(ListingStatus::Sold);
});

it('refuses a blocked customer', function (): void {
    $customer = $this->verifiedCustomer();
    $order = $this->orderFor($customer, $this->listing($this->seller(), ['price_cents' => 45000]));
    CustomerBlock::factory()->create(['customer_id' => $customer->id, 'reason' => 'Chargeback fraud.']);

    $finalize = fn () => app(FinalizeOrder::class)($order, '4242 4242 4242 4242', $this->moment('2026-08-20 10:00:00'));

    expect($finalize)->toThrow(DomainRuleViolation::class, 'Chargeback fraud.')
        ->and($order->refresh()->payments()->count())->toBe(0);
});

it('refuses to charge an order that is already paid', function (): void {
    $order = $this->orderFor($this->verifiedCustomer(), $this->listing($this->seller(), ['price_cents' => 45000]));
    $finalizeOrder = app(FinalizeOrder::class);
    $order = $finalizeOrder($order, '4242 4242 4242 4242', $this->moment('2026-08-20 10:00:00'));

    $finalizeOrder($order, '4242 4242 4242 4242', $this->moment('2026-08-20 10:05:00'));
})->throws(DomainException::class);

it('refuses to charge a cancelled order', function (): void {
    $order = $this->orderFor($this->verifiedCustomer(), $this->listing($this->seller(), ['price_cents' => 45000]));
    app(CancelOrder::class)($order, $this->moment('2026-08-20 09:30:00'));

    $finalize = fn () => app(FinalizeOrder::class)($order, '4242 4242 4242 4242', $this->moment('2026-08-20 10:00:00'));

    expect($finalize)->toThrow(DomainRuleViolation::class, 'cannot move from cancelled')
        ->and($order->refresh()->payments()->count())->toBe(0);
});

it('refuses to charge a refunded order', function (): void {
    $order = $this->paidOrderWithTwoSellers();
    foreach ($order->fulfillments()->orderBy('id')->get() as $fulfillment) {
        app(DeclineFulfillment::class)($fulfillment, 'Damaged.', $this->moment('2026-08-21 09:00:00'));
    }
    expect($order->refresh()->status)->toBe(OrderStatus::Refunded);

    $finalize = fn () => app(FinalizeOrder::class)($order, '4242 4242 4242 4242', $this->moment('2026-08-21 10:00:00'));

    expect($finalize)->toThrow(DomainRuleViolation::class, 'cannot move from refunded')
        ->and($order->refresh()->payments()->count())->toBe(1);
});
