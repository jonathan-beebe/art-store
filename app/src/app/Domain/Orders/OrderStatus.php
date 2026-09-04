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
    case Refunded = 'refunded';

    /**
     * @return list<self>
     */
    public function transitions(): array
    {
        return match ($this) {
            self::PendingVerification, self::AwaitingPayment => [self::Paid, self::PaymentFailed, self::Cancelled],
            self::PaymentFailed => [self::Paid, self::Cancelled],
            self::Paid => [self::PartiallyShipped, self::Shipped, self::Refunded],
            self::PartiallyShipped => [self::Shipped, self::Refunded],
            self::Shipped => [self::Delivered, self::Refunded],
            self::Delivered => [self::Refunded],
            self::Cancelled, self::Refunded => [],
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

    /**
     * An order awaits payment for as long as a card could still carry it to
     * paid, which is what the storefront asks before it shows a card form.
     */
    public function awaitsPayment(): bool
    {
        return $this->canTransitionTo(self::Paid);
    }

    /**
     * A declined charge put the stock back on the storefront, so a retry has to
     * claim it again before the order can be paid.
     */
    public function retakesStockOnRetry(): bool
    {
        return $this === self::PaymentFailed;
    }

    /**
     * Cancelling from here hands stock back to the storefront. A declined
     * card already returned what placement took, so only an order still
     * waiting on a card is holding any.
     */
    public function releasesStockOnCancel(): bool
    {
        return $this === self::PendingVerification || $this === self::AwaitingPayment;
    }

    /**
     * A card cleared on this order, so there is money to send back. It is
     * what a decline and a refund both ask before they issue one.
     */
    public function hasBeenPaid(): bool
    {
        return match ($this) {
            self::Paid, self::PartiallyShipped, self::Shipped, self::Delivered, self::Refunded => true,
            self::PendingVerification, self::AwaitingPayment, self::PaymentFailed, self::Cancelled => false,
        };
    }

    public function label(): string
    {
        return ucfirst(str_replace('_', ' ', $this->value));
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
     * Where a paid order stands, read from its fulfillments. Only the live
     * ones speak: a declined or refunded fulfillment has been settled in
     * money and no longer holds the order back. An order whose fulfillments
     * are all settled is itself refunded.
     *
     * @param  list<FulfillmentStatus>  $statuses
     */
    public static function fromFulfillments(array $statuses): self
    {
        if ($statuses === []) {
            throw new InvalidArgumentException('An order rolls up from at least one fulfillment.');
        }

        $live = array_values(array_filter($statuses, fn (FulfillmentStatus $status): bool => $status->isLive()));

        if ($live === []) {
            return self::Refunded;
        }

        $total = count($live);
        $delivered = count(array_filter($live, fn (FulfillmentStatus $status): bool => $status === FulfillmentStatus::Delivered));
        $departed = count(array_filter($live, fn (FulfillmentStatus $status): bool => $status->hasLeftTheStudio()));

        return match (true) {
            $delivered === $total => self::Delivered,
            $departed === $total => self::Shipped,
            $departed > 0 => self::PartiallyShipped,
            default => self::Paid,
        };
    }
}
