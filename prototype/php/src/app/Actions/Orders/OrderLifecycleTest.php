<?php

namespace App\Actions\Orders;

use App\Actions\Escrow\RunWeeklyPayout;
use App\Actions\Fulfillment\ConfirmDelivered;
use App\Actions\Fulfillment\MarkShipped;
use App\Domain\Escrow\LedgerBalance;
use App\Domain\Listings\ListingStatus;
use App\Domain\Orders\OrderStatus;
use App\Domain\Payments\PaymentStatus;
use App\Models\LedgerEntry;
use App\Models\Notification;
use App\Models\Payout;
use App\Models\Seller;
use Tests\CommerceTestCase;

/**
 * Walks the whole order lifecycle across two sellers. Every other action test
 * covers one step; these two protect the way the steps add up.
 */
final class OrderLifecycleTest extends CommerceTestCase
{
    private const APPROVED_CARD = '4242 4242 4242 4242';

    private const DECLINED_CARD = '4000 0000 0000 9995';

    public function test_an_order_runs_from_the_cart_to_the_weekly_payout(): void
    {
        $painter = $this->seller('Blue Kiln Studio');
        $printer = $this->seller('Rye Press');
        $painting = $this->listing($painter, ['price_cents' => 45000, 'quantity' => 1]);
        $print = $this->listing($printer, ['price_cents' => 12000, 'quantity' => 1]);
        $customer = $this->verifiedCustomer();

        $order = $this->orderFor($customer, $painting, $print);

        $this->assertSame(OrderStatus::AwaitingPayment, $order->status);
        $this->assertSame(57000, $order->total_cents);
        $this->assertSame(ListingStatus::Sold, $painting->refresh()->status);

        $order = app(FinalizeOrder::class)($order, self::APPROVED_CARD, $this->moment('2026-08-20 10:00:00'));

        $this->assertSame(OrderStatus::Paid, $order->status);
        $this->assertSame(2, Notification::query()->where('subject', 'Item sold')->count());
        $this->assertSame([40500, 10800], $this->heldPerSeller($painter, $printer));

        $paintingShipment = $order->fulfillments()->where('seller_id', $painter->id)->sole();
        $printShipment = $order->fulfillments()->where('seller_id', $printer->id)->sole();

        app(MarkShipped::class)($paintingShipment, 'USPS', '9400111899', $this->moment('2026-08-21 11:00:00'));
        $this->assertSame(OrderStatus::PartiallyShipped, $order->refresh()->status);

        app(MarkShipped::class)($printShipment, 'FedEx', '7712349', $this->moment('2026-08-21 12:00:00'));
        $this->assertSame(OrderStatus::Shipped, $order->refresh()->status);
        $this->assertSame(2, Notification::query()->where('subject', 'Order shipped')->count());

        app(ConfirmDelivered::class)($paintingShipment->refresh(), $this->moment('2026-08-22 09:00:00'));
        $this->assertSame(OrderStatus::Shipped, $order->refresh()->status);

        app(ConfirmDelivered::class)($printShipment->refresh(), $this->moment('2026-08-22 10:00:00'));
        $this->assertSame(OrderStatus::Delivered, $order->refresh()->status);
        $this->assertSame([40500, 10800], $this->availablePerSeller($painter, $printer));

        $payouts = app(RunWeeklyPayout::class)($this->moment('2026-08-24 09:00:00'));

        $this->assertCount(2, $payouts);
        $this->assertSame(
            [$painter->id => 40500, $printer->id => 10800],
            Payout::query()->orderBy('seller_id')->pluck('amount_cents', 'seller_id')->all(),
        );
        $this->assertSame([0, 0], $this->availablePerSeller($painter, $printer));
        $this->assertSame([0, 0], $this->heldPerSeller($painter, $printer));
        $this->assertSame([40500, 10800], $this->paidOutPerSeller($painter, $printer));
    }

    public function test_a_declined_card_returns_the_stock_and_a_retry_completes_the_order(): void
    {
        $seller = $this->seller();
        $listing = $this->listing($seller, ['price_cents' => 45000, 'quantity' => 1]);
        $customer = $this->verifiedCustomer();
        $order = $this->orderFor($customer, $listing);
        $finalizeOrder = app(FinalizeOrder::class);

        $order = $finalizeOrder($order, self::DECLINED_CARD, $this->moment('2026-08-20 10:00:00'));

        $this->assertSame(OrderStatus::PaymentFailed, $order->status);
        $this->assertSame(ListingStatus::ForSale, $listing->refresh()->status);
        $this->assertSame(1, $listing->quantity);
        $this->assertSame(0, LedgerEntry::query()->count());

        $order = $finalizeOrder($order, self::APPROVED_CARD, $this->moment('2026-08-20 10:05:00'));

        $this->assertSame(OrderStatus::Paid, $order->status);
        $this->assertSame(ListingStatus::Sold, $listing->refresh()->status);
        $this->assertSame(0, $listing->quantity);
        $this->assertSame(
            [PaymentStatus::Declined, PaymentStatus::Approved],
            $order->payments()->orderBy('id')->pluck('status')->all(),
        );
        $this->assertSame([40500], $this->heldPerSeller($seller));

        $fulfillment = app(MarkShipped::class)($order->fulfillments()->sole(), 'USPS', '9400111899', $this->moment('2026-08-21 11:00:00'));
        app(ConfirmDelivered::class)($fulfillment, $this->moment('2026-08-22 09:00:00'));

        $payouts = app(RunWeeklyPayout::class)($this->moment('2026-08-24 09:00:00'));

        $this->assertSame([40500], array_map(fn (Payout $payout): int => $payout->amount_cents, $payouts));
        $this->assertSame([40500], $this->paidOutPerSeller($seller));
    }

    /**
     * @return list<int>
     */
    private function heldPerSeller(Seller ...$sellers): array
    {
        return array_map(fn (Seller $seller): int => $this->balanceOf($seller)->held->cents, $sellers);
    }

    /**
     * @return list<int>
     */
    private function availablePerSeller(Seller ...$sellers): array
    {
        return array_map(fn (Seller $seller): int => $this->balanceOf($seller)->available->cents, $sellers);
    }

    /**
     * @return list<int>
     */
    private function paidOutPerSeller(Seller ...$sellers): array
    {
        return array_map(fn (Seller $seller): int => $this->balanceOf($seller)->paidOut->cents, $sellers);
    }

    private function balanceOf(Seller $seller): LedgerBalance
    {
        return LedgerBalance::from(
            LedgerEntry::query()
                ->where('seller_id', $seller->id)
                ->get()
                ->map(fn (LedgerEntry $entry) => $entry->toMovement())
                ->all(),
        );
    }
}
