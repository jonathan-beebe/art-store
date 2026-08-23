<?php

declare(strict_types=1);

namespace App\Actions\Orders;

use App\Domain\DomainRuleViolation;
use App\Domain\Escrow\LedgerEntryType;
use App\Domain\Listings\ListingStatus;
use App\Domain\Orders\OrderStatus;
use App\Domain\Payments\DeclineReason;
use App\Domain\Payments\PaymentStatus;
use App\Models\CustomerBlock;
use App\Models\LedgerEntry;
use App\Notifications\ItemSold;
use DomainException;
use Illuminate\Support\Facades\Notification;

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
