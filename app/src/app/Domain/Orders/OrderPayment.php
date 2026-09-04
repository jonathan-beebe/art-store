<?php

declare(strict_types=1);

namespace App\Domain\Orders;

final class OrderPayment
{
    private function __construct() {} // @codeCoverageIgnore

    /**
     * Only a verified purchaser reaches the card form: an unverified one has a
     * magic link to follow first.
     */
    public static function isPayableBy(OrderStatus $status, bool $isPurchaserVerified): bool
    {
        return $isPurchaserVerified && $status->awaitsPayment();
    }
}
