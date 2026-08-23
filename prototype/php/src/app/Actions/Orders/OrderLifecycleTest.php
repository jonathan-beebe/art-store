<?php

declare(strict_types=1);

namespace App\Actions\Orders;

use App\Actions\Escrow\RunWeeklyPayout;
use App\Actions\Fulfillment\ConfirmDelivered;
use App\Actions\Fulfillment\MarkShipped;
use App\Domain\Escrow\LedgerBalance;
use App\Domain\Listings\ListingStatus;
use App\Domain\Orders\OrderStatus;
use App\Domain\Payments\PaymentStatus;
use App\Models\LedgerEntry;
use App\Models\Payout;
use App\Models\Seller;
use App\Notifications\ItemSold;
use App\Notifications\OrderShipped;
use Illuminate\Notifications\DatabaseNotification;

/**
 * Walks the whole order lifecycle across two sellers. Every other action test
 * covers one step; these two protect the way the steps add up.
 */
it('runs an order from the cart to the weekly payout', function (): void {
    $balanceOf = fn (Seller $seller): LedgerBalance => LedgerBalance::from(
        LedgerEntry::query()
            ->where('seller_id', $seller->id)
            ->get()
            ->map(fn (LedgerEntry $entry) => $entry->toMovement())
            ->all(),
    );
    $heldPerSeller = fn (Seller ...$sellers): array => array_map(fn (Seller $seller): int => $balanceOf($seller)->held->cents, $sellers);
    $availablePerSeller = fn (Seller ...$sellers): array => array_map(fn (Seller $seller): int => $balanceOf($seller)->available->cents, $sellers);
    $paidOutPerSeller = fn (Seller ...$sellers): array => array_map(fn (Seller $seller): int => $balanceOf($seller)->paidOut->cents, $sellers);

    $painter = $this->seller('Blue Kiln Studio');
    $printer = $this->seller('Rye Press');
    $painting = $this->listing($painter, ['price_cents' => 45000, 'quantity' => 1]);
    $print = $this->listing($printer, ['price_cents' => 12000, 'quantity' => 1]);
    $customer = $this->verifiedCustomer();

    $order = $this->orderFor($customer, $painting, $print);

    expect($order->status)->toBe(OrderStatus::AwaitingPayment)
        ->and($order->total_cents)->toBe(57000);
    expect($painting)->toHaveStatus(ListingStatus::Sold);

    $order = app(FinalizeOrder::class)($order, '4242 4242 4242 4242', $this->moment('2026-08-20 10:00:00'));

    expect($order->status)->toBe(OrderStatus::Paid)
        ->and(DatabaseNotification::query()->where('type', ItemSold::class)->count())->toBe(2)
        ->and($heldPerSeller($painter, $printer))->toBe([40500, 10800]);

    $paintingShipment = $order->fulfillments()->where('seller_id', $painter->id)->sole();
    $printShipment = $order->fulfillments()->where('seller_id', $printer->id)->sole();

    app(MarkShipped::class)($paintingShipment, 'USPS', '9400111899', $this->moment('2026-08-21 11:00:00'));
    expect($order)->toHaveStatus(OrderStatus::PartiallyShipped);

    app(MarkShipped::class)($printShipment, 'FedEx', '7712349', $this->moment('2026-08-21 12:00:00'));
    expect($order)->toHaveStatus(OrderStatus::Shipped);
    expect(DatabaseNotification::query()->where('type', OrderShipped::class)->count())->toBe(2);

    app(ConfirmDelivered::class)($paintingShipment->refresh(), $this->moment('2026-08-22 09:00:00'));
    expect($order)->toHaveStatus(OrderStatus::Shipped);

    app(ConfirmDelivered::class)($printShipment->refresh(), $this->moment('2026-08-22 10:00:00'));
    expect($order)->toHaveStatus(OrderStatus::Delivered);
    expect($availablePerSeller($painter, $printer))->toBe([40500, 10800]);

    $payouts = app(RunWeeklyPayout::class)($this->moment('2026-08-24 09:00:00'));

    expect($payouts)->toHaveCount(2);
    expect(Payout::query()->orderBy('seller_id')->pluck('amount_cents', 'seller_id')->all())
        ->toBe([$painter->id => 40500, $printer->id => 10800]);
    expect($availablePerSeller($painter, $printer))->toBe([0, 0])
        ->and($heldPerSeller($painter, $printer))->toBe([0, 0])
        ->and($paidOutPerSeller($painter, $printer))->toBe([40500, 10800]);
});

it('returns the stock on a declined card and completes the order on retry', function (): void {
    $balanceOf = fn (Seller $seller): LedgerBalance => LedgerBalance::from(
        LedgerEntry::query()
            ->where('seller_id', $seller->id)
            ->get()
            ->map(fn (LedgerEntry $entry) => $entry->toMovement())
            ->all(),
    );
    $heldPerSeller = fn (Seller ...$sellers): array => array_map(fn (Seller $seller): int => $balanceOf($seller)->held->cents, $sellers);
    $paidOutPerSeller = fn (Seller ...$sellers): array => array_map(fn (Seller $seller): int => $balanceOf($seller)->paidOut->cents, $sellers);

    $seller = $this->seller();
    $listing = $this->listing($seller, ['price_cents' => 45000, 'quantity' => 1]);
    $customer = $this->verifiedCustomer();
    $order = $this->orderFor($customer, $listing);
    $finalizeOrder = app(FinalizeOrder::class);

    $order = $finalizeOrder($order, '4000 0000 0000 9995', $this->moment('2026-08-20 10:00:00'));

    expect($order->status)->toBe(OrderStatus::PaymentFailed);
    expect($listing)->toHaveStatus(ListingStatus::ForSale);
    expect($listing->fresh()->quantity)->toBe(1)
        ->and(LedgerEntry::query()->count())->toBe(0);

    $order = $finalizeOrder($order, '4242 4242 4242 4242', $this->moment('2026-08-20 10:05:00'));

    expect($order->status)->toBe(OrderStatus::Paid);
    expect($listing)->toHaveStatus(ListingStatus::Sold);
    expect($listing->fresh()->quantity)->toBe(0)
        ->and($order->payments()->orderBy('id')->pluck('status')->all())->toBe([PaymentStatus::Declined, PaymentStatus::Approved])
        ->and($heldPerSeller($seller))->toBe([40500]);

    $fulfillment = app(MarkShipped::class)($order->fulfillments()->sole(), 'USPS', '9400111899', $this->moment('2026-08-21 11:00:00'));
    app(ConfirmDelivered::class)($fulfillment, $this->moment('2026-08-22 09:00:00'));

    $payouts = app(RunWeeklyPayout::class)($this->moment('2026-08-24 09:00:00'));

    expect(array_map(fn (Payout $payout): int => $payout->amount_cents, $payouts))->toBe([40500])
        ->and($paidOutPerSeller($seller))->toBe([40500]);
});
