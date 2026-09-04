<?php

declare(strict_types=1);

namespace App\Domain\Orders;

use App\Domain\DomainRuleViolation;

enum FulfillmentStatus: string
{
    case AwaitingShipment = 'awaiting_shipment';
    case Shipped = 'shipped';
    case Delivered = 'delivered';
    case Declined = 'declined';
    case Refunded = 'refunded';

    /**
     * @return list<self>
     */
    public function transitions(): array
    {
        return match ($this) {
            self::AwaitingShipment => [self::Shipped, self::Declined, self::Refunded],
            self::Shipped => [self::Delivered, self::Refunded],
            self::Delivered => [self::Refunded],
            self::Declined, self::Refunded => [],
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
            : throw new DomainRuleViolation("A fulfillment cannot move from {$this->value} to {$next->value}.");
    }

    public function hasLeftTheStudio(): bool
    {
        return $this === self::Shipped || $this === self::Delivered;
    }

    /**
     * A fulfillment the order still rolls up from. A declined or refunded one
     * is settled: the money went back and the order reads as if the line were
     * never part of it.
     */
    public function isLive(): bool
    {
        return $this !== self::Declined && $this !== self::Refunded;
    }

    public function label(): string
    {
        return ucfirst(str_replace('_', ' ', $this->value));
    }

    /**
     * The color the status badge wears. Named here so the row, the header,
     * and the badge on one page cannot drift apart.
     */
    public function tint(): string
    {
        return match ($this) {
            self::AwaitingShipment => 'yellow',
            self::Shipped => 'blue',
            self::Delivered => 'green',
            self::Refunded => 'red',
            self::Declined => 'gray',
        };
    }
}
