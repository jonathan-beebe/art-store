<?php

declare(strict_types=1);

namespace App\Domain\Escrow;

use App\Domain\Money\Money;

final class Fee
{
    public const PLATFORM_PERCENT = 10;

    public static function platform(Money $subtotal): Money
    {
        return $subtotal->percent(self::PLATFORM_PERCENT);
    }

    public static function net(Money $subtotal): Money
    {
        return $subtotal->subtract(self::platform($subtotal));
    }
}
