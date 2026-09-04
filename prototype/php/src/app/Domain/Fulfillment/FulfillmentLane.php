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
        return self::forStarted($status, $progress->hasStarted());
    }

    /**
     * The same rule read from the two facts alone, for a caller that counted
     * parcels instead of walking their flows: the lane counts come from one
     * grouped query over status and "has a completed step", and the progress
     * behind each row is never built.
     */
    public static function forStarted(FulfillmentStatus $status, bool $hasStarted): self
    {
        return match ($status) {
            FulfillmentStatus::AwaitingShipment => $hasStarted ? self::InProgress : self::ToShip,
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
