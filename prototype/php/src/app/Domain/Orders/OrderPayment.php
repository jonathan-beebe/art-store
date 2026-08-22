<?php

namespace App\Domain\Orders;

final class OrderPayment
{
    /**
     * An order awaits payment for as long as a card could still carry it to
     * paid, which is what the storefront asks before it shows a card form.
     */
    public static function awaitsPayment(OrderStatus $status): bool
    {
        return $status->canTransitionTo(OrderStatus::Paid);
    }

    public static function isPayableBy(OrderStatus $status, bool $isPurchaserVerified): bool
    {
        return $isPurchaserVerified && self::awaitsPayment($status);
    }
}
