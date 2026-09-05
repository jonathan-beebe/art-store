<?php

declare(strict_types=1);

namespace App\Domain\Seller;

/**
 * The four things that happen between a seller and one buyer. The feed's
 * filter narrows to one of them.
 */
enum ActivityKind: string
{
    case Browse = 'browse';
    case Order = 'order';
    case Shipping = 'shipping';
    case Messages = 'messages';

    public function label(): string
    {
        return match ($this) {
            self::Browse => 'Browsing',
            self::Order => 'Order',
            self::Shipping => 'Shipping',
            self::Messages => 'Messages',
        };
    }
}
