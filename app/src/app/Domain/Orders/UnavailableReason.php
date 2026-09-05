<?php

declare(strict_types=1);

namespace App\Domain\Orders;

/**
 * Why a line stood in the way of becoming an order, in the vocabulary
 * docs/spec.md §4 fixes so the wire and the log read alike.
 */
enum UnavailableReason: string
{
    case Removed = 'removed';
    case OffSale = 'off_sale';
    case SoldOut = 'sold_out';
    case ShortStock = 'short_stock';

    /**
     * What the checkout and pay pages say about a line carrying this reason.
     */
    public function notice(): string
    {
        return match ($this) {
            self::Removed => 'no longer available',
            self::OffSale => 'no longer for sale',
            self::SoldOut => 'sold out',
            self::ShortStock => 'no longer in stock in that quantity',
        };
    }
}
