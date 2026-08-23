<?php

declare(strict_types=1);

namespace App\Domain\Orders;

use DomainException;

enum FulfillmentStatus: string
{
    case AwaitingShipment = 'awaiting_shipment';
    case Shipped = 'shipped';
    case Delivered = 'delivered';

    /**
     * @return list<self>
     */
    public function transitions(): array
    {
        return match ($this) {
            self::AwaitingShipment => [self::Shipped],
            self::Shipped => [self::Delivered],
            self::Delivered => [],
        };
    }

    public function canTransitionTo(self $next): bool
    {
        return in_array($next, $this->transitions(), true);
    }

    public function transitionTo(self $next): self
    {
        return $this->canTransitionTo($next)
            ? $next
            : throw new DomainException("A fulfillment cannot move from {$this->value} to {$next->value}.");
    }

    public function hasLeftTheStudio(): bool
    {
        return $this === self::Shipped || $this === self::Delivered;
    }
}
