<?php

namespace App\Domain\Orders;

use DomainException;
use PHPUnit\Framework\TestCase;

final class FulfillmentStatusTest extends TestCase
{
    public function test_a_fulfillment_awaiting_shipment_ships_next(): void
    {
        $this->assertSame([FulfillmentStatus::Shipped], FulfillmentStatus::AwaitingShipment->transitions());
    }

    public function test_a_shipped_fulfillment_is_delivered_next(): void
    {
        $this->assertSame([FulfillmentStatus::Delivered], FulfillmentStatus::Shipped->transitions());
    }

    public function test_a_delivered_fulfillment_is_final(): void
    {
        $this->assertSame([], FulfillmentStatus::Delivered->transitions());
    }

    public function test_can_transition_to_agrees_with_the_transition_table(): void
    {
        foreach (FulfillmentStatus::cases() as $from) {
            foreach (FulfillmentStatus::cases() as $to) {
                $this->assertSame(
                    in_array($to, $from->transitions(), true),
                    $from->canTransitionTo($to),
                    "{$from->value} -> {$to->value}",
                );
            }
        }
    }

    public function test_transition_to_returns_the_next_status(): void
    {
        $this->assertSame(
            FulfillmentStatus::Shipped,
            FulfillmentStatus::AwaitingShipment->transitionTo(FulfillmentStatus::Shipped),
        );
    }

    public function test_transition_to_rejects_shipping_a_delivered_fulfillment(): void
    {
        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('delivered to shipped');

        FulfillmentStatus::Delivered->transitionTo(FulfillmentStatus::Shipped);
    }
}
