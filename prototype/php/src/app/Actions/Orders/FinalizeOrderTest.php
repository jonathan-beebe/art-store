<?php

declare(strict_types=1);

namespace App\Actions\Orders;

use App\Domain\Escrow\LedgerEntryType;
use App\Domain\Listings\ListingStatus;
use App\Domain\Orders\OrderStatus;
use App\Domain\Payments\DeclineReason;
use App\Domain\Payments\PaymentStatus;
use App\Models\LedgerEntry;
use App\Models\Notification;
use DomainException;
use Tests\CommerceTestCase;

final class FinalizeOrderTest extends CommerceTestCase
{
    private const APPROVED_CARD = '4242 4242 4242 4242';

    private const DECLINED_CARD = '4000 0000 0000 0002';

    public function test_an_approved_card_pays_the_order(): void
    {
        $order = $this->orderFor($this->verifiedCustomer(), $this->listing($this->seller(), ['price_cents' => 45000]));

        $order = app(FinalizeOrder::class)($order, self::APPROVED_CARD, $this->moment('2026-08-20 10:00:00'));

        $this->assertSame(OrderStatus::Paid, $order->status);
        $this->assertSame('2026-08-20 10:00:00', $order->finalized_at->format('Y-m-d H:i:s'));
    }

    public function test_an_approved_card_records_the_payment(): void
    {
        $order = $this->orderFor($this->verifiedCustomer(), $this->listing($this->seller(), ['price_cents' => 45000]));

        app(FinalizeOrder::class)($order, self::APPROVED_CARD, $this->moment('2026-08-20 10:00:00'));

        $payment = $order->payments()->sole();
        $this->assertSame(PaymentStatus::Approved, $payment->status);
        $this->assertSame(45000, $payment->amount_cents);
        $this->assertSame('4242', $payment->card_last_four);
        $this->assertNull($payment->decline_reason);
    }

    public function test_a_paid_order_holds_the_seller_net_in_escrow(): void
    {
        $seller = $this->seller();
        $order = $this->orderFor($this->verifiedCustomer(), $this->listing($seller, ['price_cents' => 45000]));

        app(FinalizeOrder::class)($order, self::APPROVED_CARD, $this->moment('2026-08-20 10:00:00'));

        $entry = LedgerEntry::query()->sole();
        $this->assertSame(LedgerEntryType::Held, $entry->type);
        $this->assertSame(40500, $entry->amount_cents);
        $this->assertSame($seller->id, $entry->seller_id);
        $this->assertSame($order->fulfillments()->sole()->id, $entry->fulfillment_id);
    }

    public function test_a_paid_order_holds_one_amount_per_seller(): void
    {
        $order = $this->orderFor(
            $this->verifiedCustomer(),
            $this->listing($this->seller('Blue Kiln Studio'), ['price_cents' => 45000]),
            $this->listing($this->seller('Rye Press'), ['price_cents' => 10000]),
        );

        app(FinalizeOrder::class)($order, self::APPROVED_CARD, $this->moment('2026-08-20 10:00:00'));

        $this->assertSame([9000, 40500], LedgerEntry::query()->orderBy('amount_cents')->pluck('amount_cents')->all());
    }

    public function test_a_paid_order_tells_each_seller_their_item_sold(): void
    {
        $seller = $this->seller();
        $order = $this->orderFor($this->verifiedCustomer(), $this->listing($seller, ['price_cents' => 45000]));

        app(FinalizeOrder::class)($order, self::APPROVED_CARD, $this->moment('2026-08-20 10:00:00'));

        $notification = Notification::query()->sole();
        $this->assertSame($seller->id, $notification->seller_id);
        $this->assertSame('Item sold', $notification->subject);
        $this->assertStringContainsString('$405.00', $notification->body);
    }

    public function test_a_declined_card_fails_the_payment(): void
    {
        $order = $this->orderFor($this->verifiedCustomer(), $this->listing($this->seller(), ['price_cents' => 45000]));

        $order = app(FinalizeOrder::class)($order, self::DECLINED_CARD, $this->moment('2026-08-20 10:00:00'));

        $this->assertSame(OrderStatus::PaymentFailed, $order->status);
        $this->assertNull($order->finalized_at);
        $this->assertSame(DeclineReason::GenericDecline, $order->payments()->sole()->decline_reason);
    }

    public function test_a_declined_card_puts_the_stock_back_on_the_storefront(): void
    {
        $listing = $this->listing($this->seller(), ['price_cents' => 45000, 'quantity' => 1]);
        $order = $this->orderFor($this->verifiedCustomer(), $listing);

        app(FinalizeOrder::class)($order, self::DECLINED_CARD, $this->moment('2026-08-20 10:00:00'));

        $listing->refresh();
        $this->assertSame(1, $listing->quantity);
        $this->assertSame(ListingStatus::ForSale, $listing->status);
    }

    public function test_a_declined_card_holds_nothing_and_tells_nobody(): void
    {
        $order = $this->orderFor($this->verifiedCustomer(), $this->listing($this->seller(), ['price_cents' => 45000]));

        app(FinalizeOrder::class)($order, self::DECLINED_CARD, $this->moment('2026-08-20 10:00:00'));

        $this->assertSame(0, LedgerEntry::query()->count());
        $this->assertSame(0, Notification::query()->count());
    }

    public function test_a_retry_with_a_good_card_pays_the_order_and_takes_the_stock_again(): void
    {
        $listing = $this->listing($this->seller(), ['price_cents' => 45000, 'quantity' => 1]);
        $order = $this->orderFor($this->verifiedCustomer(), $listing);
        $finalizeOrder = app(FinalizeOrder::class);
        $finalizeOrder($order, self::DECLINED_CARD, $this->moment('2026-08-20 10:00:00'));

        $order = $finalizeOrder($order, self::APPROVED_CARD, $this->moment('2026-08-20 10:05:00'));

        $listing->refresh();
        $this->assertSame(OrderStatus::Paid, $order->status);
        $this->assertSame(0, $listing->quantity);
        $this->assertSame(ListingStatus::Sold, $listing->status);
        $this->assertSame(2, $order->payments()->count());
        $this->assertSame(40500, LedgerEntry::query()->sole()->amount_cents);
    }

    public function test_it_refuses_to_charge_an_order_that_is_already_paid(): void
    {
        $order = $this->orderFor($this->verifiedCustomer(), $this->listing($this->seller(), ['price_cents' => 45000]));
        $finalizeOrder = app(FinalizeOrder::class);
        $order = $finalizeOrder($order, self::APPROVED_CARD, $this->moment('2026-08-20 10:00:00'));

        $this->expectException(DomainException::class);

        $finalizeOrder($order, self::APPROVED_CARD, $this->moment('2026-08-20 10:05:00'));
    }
}
