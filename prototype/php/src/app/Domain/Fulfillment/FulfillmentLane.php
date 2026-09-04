<?php

declare(strict_types=1);

namespace App\Domain\Fulfillment;

use App\Domain\Orders\FulfillmentStatus;

/**
 * Which pile of the seller's desk a parcel sits on. The status alone cannot
 * say: two parcels both awaiting shipment are in different piles once one of
 * them has a step behind it.
 */
enum FulfillmentLane: string
{
    case ToShip = 'ship';
    case InProgress = 'progress';
    case Done = 'done';

    public static function of(FulfillmentStatus $status, FulfillmentProgress $progress): self
    {
        return match ($status) {
            FulfillmentStatus::AwaitingShipment => $progress->hasStarted() ? self::InProgress : self::ToShip,
            FulfillmentStatus::Shipped => self::InProgress,
            FulfillmentStatus::Delivered, FulfillmentStatus::Declined, FulfillmentStatus::Refunded => self::Done,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::ToShip => 'To ship',
            self::InProgress => 'In progress',
            self::Done => 'Done',
        };
    }
}
