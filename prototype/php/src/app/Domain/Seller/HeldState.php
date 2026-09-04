<?php

declare(strict_types=1);

namespace App\Domain\Seller;

use App\Domain\Orders\FulfillmentStatus;

/**
 * Why a held order's money has not released yet, read off `shipped_at`
 * alone: a fulfillment either has not shipped or has, with no finer step in
 * between. The seller's own flow steps (label printed, packed) append their
 * own events elsewhere; this reads only the two columns every fulfillment
 * already carries.
 */
enum HeldState
{
    case NotYetShipped;
    case InTransit;

    public static function of(FulfillmentStatus $status): self
    {
        return $status === FulfillmentStatus::Shipped ? self::InTransit : self::NotYetShipped;
    }
}
