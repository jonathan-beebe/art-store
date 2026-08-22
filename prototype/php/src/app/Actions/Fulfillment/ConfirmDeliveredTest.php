<?php

namespace App\Actions\Fulfillment;

use App\Actions\Orders\FinalizeOrder;
use App\Domain\Escrow\LedgerEntryType;
use App\Domain\Orders\FulfillmentStatus;
use App\Domain\Orders\OrderStatus;
use App\Models\Fulfillment;
use App\Models\LedgerEntry;
use DomainException;
use Tests\CommerceTestCase;

final class ConfirmDeliveredTest extends CommerceTestCase
{
    public function test_it_records_when_the_parcel_arrived(): void
    {
        $fulfillment = $this->shippedFulfillment();

        $fulfillment = app(ConfirmDelivered::class)($fulfillment, $this->moment('2026-08-23 14:00:00'));

        $this->assertSame(FulfillmentStatus::Delivered, $fulfillment->status);
        $this->assertSame('2026-08-23 14:00:00', $fulfillment->delivered_at->format('Y-m-d H:i:s'));
    }

    public function test_delivery_releases_the_escrow_hold(): void
    {
        $fulfillment = $this->shippedFulfillment();

        app(ConfirmDelivered::class)($fulfillment, $this->moment('2026-08-23 14:00:00'));

        $entry = LedgerEntry::query()->where('type', LedgerEntryType::Released)->sole();
        $this->assertSame(40500, $entry->amount_cents);
        $this->assertSame($fulfillment->seller_id, $entry->seller_id);
        $this->assertSame($fulfillment->id, $entry->fulfillment_id);
        $this->assertSame('2026-08-23 14:00:00', $entry->occurred_at->format('Y-m-d H:i:s'));
    }

    public function test_the_last_delivery_delivers_the_order(): void
    {
        $fulfillment = $this->shippedFulfillment();

        app(ConfirmDelivered::class)($fulfillment, $this->moment('2026-08-23 14:00:00'));

        $this->assertSame(OrderStatus::Delivered, $fulfillment->order->refresh()->status);
    }

    public function test_it_refuses_a_fulfillment_that_never_shipped(): void
    {
        $order = app(FinalizeOrder::class)(
            $this->orderFor($this->verifiedCustomer(), $this->listing($this->seller(), ['price_cents' => 45000])),
            '4242 4242 4242 4242',
            $this->moment('2026-08-20 10:00:00'),
        );

        $this->expectException(DomainException::class);

        app(ConfirmDelivered::class)($order->fulfillments()->sole(), $this->moment('2026-08-23 14:00:00'));
    }

    private function shippedFulfillment(): Fulfillment
    {
        $order = app(FinalizeOrder::class)(
            $this->orderFor($this->verifiedCustomer(), $this->listing($this->seller(), ['price_cents' => 45000])),
            '4242 4242 4242 4242',
            $this->moment('2026-08-20 10:00:00'),
        );

        return app(MarkShipped::class)($order->fulfillments()->sole(), 'USPS', '9400111899', $this->moment('2026-08-21 11:00:00'));
    }
}
