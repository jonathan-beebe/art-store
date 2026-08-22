<?php

namespace App\Domain\Orders;

use PHPUnit\Framework\TestCase;

final class OrderPaymentTest extends TestCase
{
    public function test_an_order_before_a_successful_charge_still_awaits_payment(): void
    {
        $this->assertTrue(OrderPayment::awaitsPayment(OrderStatus::PendingVerification));
        $this->assertTrue(OrderPayment::awaitsPayment(OrderStatus::AwaitingPayment));
        $this->assertTrue(OrderPayment::awaitsPayment(OrderStatus::PaymentFailed));
    }

    public function test_a_paid_order_no_longer_awaits_payment(): void
    {
        $this->assertFalse(OrderPayment::awaitsPayment(OrderStatus::Paid));
        $this->assertFalse(OrderPayment::awaitsPayment(OrderStatus::Shipped));
        $this->assertFalse(OrderPayment::awaitsPayment(OrderStatus::Delivered));
        $this->assertFalse(OrderPayment::awaitsPayment(OrderStatus::Cancelled));
    }

    public function test_only_a_verified_purchaser_may_pay(): void
    {
        $this->assertTrue(OrderPayment::isPayableBy(OrderStatus::PendingVerification, true));
        $this->assertFalse(OrderPayment::isPayableBy(OrderStatus::PendingVerification, false));
    }

    public function test_a_verified_purchaser_may_not_pay_a_paid_order(): void
    {
        $this->assertFalse(OrderPayment::isPayableBy(OrderStatus::Paid, true));
    }
}
