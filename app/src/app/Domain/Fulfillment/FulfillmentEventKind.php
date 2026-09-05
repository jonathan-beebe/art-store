<?php

declare(strict_types=1);

namespace App\Domain\Fulfillment;

use App\Domain\DomainRuleViolation;
use App\Domain\Orders\FulfillmentStatus;

/**
 * What one row of a fulfillment's log records: a step the seller completed,
 * or the transition that moved `fulfillments.status`.
 */
enum FulfillmentEventKind: string
{
    case StepCompleted = 'step_completed';
    case Shipped = 'shipped';
    case Delivered = 'delivered';
    case Declined = 'declined';
    case Refunded = 'refunded';

    /**
     * The kind that records a move into $status. Every status a fulfillment
     * can be moved into has one, so the log cannot fall behind the column.
     * `awaiting_shipment` is where a fulfillment starts and no transition
     * reaches it.
     *
     * @throws DomainRuleViolation
     */
    public static function forStatus(FulfillmentStatus $status): self
    {
        return match ($status) {
            FulfillmentStatus::AwaitingShipment => throw new DomainRuleViolation('A fulfillment cannot be moved into awaiting_shipment.'),
            FulfillmentStatus::Shipped => self::Shipped,
            FulfillmentStatus::Delivered => self::Delivered,
            FulfillmentStatus::Declined => self::Declined,
            FulfillmentStatus::Refunded => self::Refunded,
        };
    }

    public function namesAStep(): bool
    {
        return $this === self::StepCompleted;
    }

    public function label(): string
    {
        return match ($this) {
            self::StepCompleted => 'Step completed',
            self::Shipped => 'Shipped',
            self::Delivered => 'Delivered',
            self::Declined => 'Declined',
            self::Refunded => 'Refunded',
        };
    }
}
