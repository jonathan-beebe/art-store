<?php

namespace App\Domain\Orders;

use App\Domain\Payments\CardDecision;
use App\Domain\Payments\DeclineReason;
use DateTimeImmutable;
use DomainException;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class OrderStatusTest extends TestCase
{
    public function test_an_unverified_order_may_be_charged_or_cancelled(): void
    {
        $this->assertSame(
            [OrderStatus::Paid, OrderStatus::PaymentFailed, OrderStatus::Cancelled],
            OrderStatus::PendingVerification->transitions(),
        );
    }

    public function test_an_order_awaiting_payment_may_be_charged_or_cancelled(): void
    {
        $this->assertSame(
            [OrderStatus::Paid, OrderStatus::PaymentFailed, OrderStatus::Cancelled],
            OrderStatus::AwaitingPayment->transitions(),
        );
    }

    public function test_a_failed_payment_may_be_retried_or_cancelled(): void
    {
        $this->assertSame(
            [OrderStatus::Paid, OrderStatus::Cancelled],
            OrderStatus::PaymentFailed->transitions(),
        );
    }

    public function test_a_paid_order_moves_into_shipping(): void
    {
        $this->assertSame(
            [OrderStatus::PartiallyShipped, OrderStatus::Shipped],
            OrderStatus::Paid->transitions(),
        );
    }

    public function test_a_partially_shipped_order_completes_shipping(): void
    {
        $this->assertSame([OrderStatus::Shipped], OrderStatus::PartiallyShipped->transitions());
    }

    public function test_a_shipped_order_is_delivered_next(): void
    {
        $this->assertSame([OrderStatus::Delivered], OrderStatus::Shipped->transitions());
    }

    public function test_delivered_and_cancelled_are_final(): void
    {
        $this->assertSame([], OrderStatus::Delivered->transitions());
        $this->assertSame([], OrderStatus::Cancelled->transitions());
    }

    public function test_can_transition_to_agrees_with_the_transition_table(): void
    {
        foreach (OrderStatus::cases() as $from) {
            foreach (OrderStatus::cases() as $to) {
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
        $this->assertSame(OrderStatus::Paid, OrderStatus::PaymentFailed->transitionTo(OrderStatus::Paid));
    }

    public function test_transition_to_rejects_a_move_outside_the_table(): void
    {
        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('delivered to paid');

        OrderStatus::Delivered->transitionTo(OrderStatus::Paid);
    }

    public function test_a_verified_purchaser_places_an_order_that_is_ready_to_charge(): void
    {
        $purchaser = new Purchaser(1, 'buyer@example.test', new DateTimeImmutable('2026-08-22 10:00:00'));

        $this->assertSame(OrderStatus::AwaitingPayment, OrderStatus::forPlacement($purchaser));
    }

    public function test_an_unverified_purchaser_places_an_order_that_waits_for_verification(): void
    {
        $purchaser = new Purchaser(1, null, null);

        $this->assertSame(OrderStatus::PendingVerification, OrderStatus::forPlacement($purchaser));
    }

    public function test_an_approved_card_pays_the_order(): void
    {
        $this->assertSame(
            OrderStatus::Paid,
            OrderStatus::fromCardDecision(CardDecision::approved('4242')),
        );
    }

    public function test_a_declined_card_fails_the_payment(): void
    {
        $this->assertSame(
            OrderStatus::PaymentFailed,
            OrderStatus::fromCardDecision(CardDecision::declined('0002', DeclineReason::GenericDecline)),
        );
    }

    public function test_fulfillments_all_awaiting_shipment_leave_the_order_paid(): void
    {
        $this->assertSame(
            OrderStatus::Paid,
            OrderStatus::fromFulfillments([FulfillmentStatus::AwaitingShipment, FulfillmentStatus::AwaitingShipment]),
        );
    }

    public function test_one_shipped_fulfillment_of_several_partially_ships_the_order(): void
    {
        $this->assertSame(
            OrderStatus::PartiallyShipped,
            OrderStatus::fromFulfillments([FulfillmentStatus::Shipped, FulfillmentStatus::AwaitingShipment]),
        );
    }

    public function test_one_delivered_fulfillment_of_several_partially_ships_the_order(): void
    {
        $this->assertSame(
            OrderStatus::PartiallyShipped,
            OrderStatus::fromFulfillments([FulfillmentStatus::Delivered, FulfillmentStatus::AwaitingShipment]),
        );
    }

    public function test_every_fulfillment_shipped_ships_the_order(): void
    {
        $this->assertSame(
            OrderStatus::Shipped,
            OrderStatus::fromFulfillments([FulfillmentStatus::Shipped, FulfillmentStatus::Shipped]),
        );
    }

    public function test_a_mix_of_shipped_and_delivered_ships_the_order(): void
    {
        $this->assertSame(
            OrderStatus::Shipped,
            OrderStatus::fromFulfillments([FulfillmentStatus::Delivered, FulfillmentStatus::Shipped]),
        );
    }

    public function test_every_fulfillment_delivered_delivers_the_order(): void
    {
        $this->assertSame(
            OrderStatus::Delivered,
            OrderStatus::fromFulfillments([FulfillmentStatus::Delivered, FulfillmentStatus::Delivered]),
        );
    }

    public function test_a_roll_up_needs_at_least_one_fulfillment(): void
    {
        $this->expectException(InvalidArgumentException::class);

        OrderStatus::fromFulfillments([]);
    }
}
