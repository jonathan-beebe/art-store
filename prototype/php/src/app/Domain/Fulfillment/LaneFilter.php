<?php

declare(strict_types=1);

namespace App\Domain\Fulfillment;

/**
 * The orders tool's `?lane=`: one of the three piles a parcel sits on, or
 * every parcel there is. `All` is the tab with no lane behind it, which is
 * why this carries its own vocabulary instead of a nullable
 * {@see FulfillmentLane}.
 */
enum LaneFilter: string
{
    case ToShip = 'ship';
    case InProgress = 'progress';
    case Done = 'done';
    case All = 'all';

    public static function default(): self
    {
        return self::ToShip;
    }

    public static function of(FulfillmentLane $lane): self
    {
        return match ($lane) {
            FulfillmentLane::ToShip => self::ToShip,
            FulfillmentLane::InProgress => self::InProgress,
            FulfillmentLane::Done => self::Done,
        };
    }

    /**
     * The pile this tab narrows to, and null for the tab that narrows to
     * nothing.
     */
    public function lane(): ?FulfillmentLane
    {
        return match ($this) {
            self::ToShip => FulfillmentLane::ToShip,
            self::InProgress => FulfillmentLane::InProgress,
            self::Done => FulfillmentLane::Done,
            self::All => null,
        };
    }

    public function label(): string
    {
        return $this->lane()?->label() ?? 'All';
    }

    /**
     * Whether the tab counts what it holds. A seller acts on To ship and In
     * progress, so those wear a number; Done and All are archives.
     */
    public function isCounted(): bool
    {
        return $this === self::ToShip || $this === self::InProgress;
    }

    /**
     * Which end of the queue the list opens on. The oldest unshipped parcel
     * is the one keeping a buyer waiting, so To ship reads oldest first;
     * every other tab reads newest first.
     */
    public function oldestFirst(): bool
    {
        return $this === self::ToShip;
    }

    public function emptyMessage(): string
    {
        return match ($this) {
            self::ToShip => 'Nothing to ship.',
            self::InProgress => 'Nothing on its way.',
            self::Done => 'Nothing finished yet.',
            self::All => 'No orders yet.',
        };
    }
}
