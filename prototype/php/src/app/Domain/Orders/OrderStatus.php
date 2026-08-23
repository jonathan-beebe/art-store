<?php

declare(strict_types=1);

namespace App\Domain\Orders;

use App\Domain\DomainRuleViolation;
use App\Domain\Payments\PaymentOutcome;
use InvalidArgumentException;

enum OrderStatus: string
{
    case PendingVerification = 'pending_verification';
    case AwaitingPayment = 'awaiting_payment';
    case Paid = 'paid';
    case PaymentFailed = 'payment_failed';
    case PartiallyShipped = 'partially_shipped';
    case Shipped = 'shipped';
    case Delivered = 'delivered';
    case Cancelled = 'cancelled';

    /**
     * @return list<self>
     */
    public function transitions(): array
    {
        return match ($this) {
            self::PendingVerification, self::AwaitingPayment => [self::Paid, self::PaymentFailed, self::Cancelled],
            self::PaymentFailed => [self::Paid, self::Cancelled],
            self::Paid => [self::PartiallyShipped, self::Shipped],
            self::PartiallyShipped => [self::Shipped],
            self::Shipped => [self::Delivered],
            self::Delivered, self::Cancelled => [],
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
            : throw new DomainRuleViolation("An order cannot move from {$this->value} to {$next->value}.");
    }

    public static function forPlacement(Purchaser $purchaser): self
    {
        return $purchaser->isEmailVerified() ? self::AwaitingPayment : self::PendingVerification;
    }

    public static function fromCardDecision(PaymentOutcome $outcome): self
    {
        return match ($outcome) {
            PaymentOutcome::Approved => self::Paid,
            PaymentOutcome::Declined => self::PaymentFailed,
        };
    }

    /**
     * @param  list<FulfillmentStatus>  $statuses
     */
    public static function fromFulfillments(array $statuses): self
    {
        if ($statuses === []) {
            throw new InvalidArgumentException('An order rolls up from at least one fulfillment.');
        }

        $delivered = array_filter($statuses, fn (FulfillmentStatus $status): bool => $status === FulfillmentStatus::Delivered);
        $departed = array_filter($statuses, fn (FulfillmentStatus $status): bool => $status->hasLeftTheStudio());

        return match (true) {
            count($delivered) === count($statuses) => self::Delivered,
            count($departed) === count($statuses) => self::Shipped,
            count($departed) > 0 => self::PartiallyShipped,
            default => self::Paid,
        };
    }
}
