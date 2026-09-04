<?php

declare(strict_types=1);

namespace App\Domain\Fulfillment;

use App\Domain\Escrow\LedgerEntryType;
use App\Domain\Money\Money;
use App\Domain\Orders\FulfillmentStatus;
use DateTimeImmutable;

/**
 * The sentence under a buyer's name on the order page: where the parcel is,
 * how long it has been there, and — once it is settled — what happened to
 * the money. One line for every status, built from facts alone, so the page
 * says the same thing the status badge does at more length.
 */
final readonly class ParcelState
{
    /**
     * Ship by is a display rule: three days from the order, never a stored
     * date.
     */
    private const int SHIP_WITHIN_DAYS = 3;

    public function __construct(
        private FulfillmentStatus $status,
        private DateTimeImmutable $placedAt,
        private DateTimeImmutable $now,
        private ?string $lastStepLabel = null,
        private ?DateTimeImmutable $lastStepAt = null,
        private ?string $carrier = null,
        private ?DateTimeImmutable $shippedAt = null,
        private ?DateTimeImmutable $settledAt = null,
        private ?Money $settledAmount = null,
        private ?LedgerEntryType $escrow = null,
    ) {}

    public function line(): string
    {
        return match ($this->status) {
            FulfillmentStatus::AwaitingShipment => $this->awaitingLine(),
            FulfillmentStatus::Shipped => $this->shippedLine(),
            FulfillmentStatus::Delivered => $this->settledLine('Delivered'),
            FulfillmentStatus::Declined => $this->settledLine('Declined'),
            FulfillmentStatus::Refunded => $this->settledLine('Refunded'),
        };
    }

    /**
     * A parcel still in the studio reads as the clock the buyer is watching
     * until a step is behind it, and as the step after that.
     */
    private function awaitingLine(): string
    {
        if ($this->lastStepLabel === null || $this->lastStepAt === null) {
            $shipBy = $this->placedAt->modify('+'.self::SHIP_WITHIN_DAYS.' days');

            return 'Placed '.$this->elapsedSince($this->placedAt).' · ship by '.$shipBy->format('M j');
        }

        return $this->lastStepLabel.' '.$this->elapsedSince($this->lastStepAt).' · waiting for the parcel to leave';
    }

    private function shippedLine(): string
    {
        $carrier = $this->carrier ?? 'the carrier';

        return $this->shippedAt === null
            ? "In transit with {$carrier}"
            : "In transit with {$carrier} since ".$this->shippedAt->format('M j');
    }

    private function settledLine(string $verb): string
    {
        $line = $this->settledAt === null ? $verb : $verb.' '.$this->settledAt->format('M j');

        if (! $this->settledAmount instanceof Money || ! $this->escrow instanceof LedgerEntryType) {
            return $line;
        }

        return $line.' · '.$this->settledAmount->format().' '.$this->moneyPhrase($this->escrow);
    }

    private function moneyPhrase(LedgerEntryType $escrow): string
    {
        return match ($escrow) {
            LedgerEntryType::Held => 'held in escrow',
            LedgerEntryType::Released => 'released to your balance',
            LedgerEntryType::PaidOut => 'paid out',
            LedgerEntryType::Refunded => 'returned to the buyer',
        };
    }

    private function elapsedSince(DateTimeImmutable $at): string
    {
        $seconds = $this->now->getTimestamp() - $at->getTimestamp();
        $minutes = intdiv($seconds, 60);

        if ($minutes < 1) {
            return 'just now';
        }

        if ($minutes < 60) {
            return self::plural($minutes, 'minute');
        }

        $hours = intdiv($minutes, 60);

        return $hours < 24 ? self::plural($hours, 'hour') : self::plural(intdiv($hours, 24), 'day');
    }

    private static function plural(int $count, string $unit): string
    {
        return $count === 1 ? "1 {$unit} ago" : "{$count} {$unit}s ago";
    }
}
