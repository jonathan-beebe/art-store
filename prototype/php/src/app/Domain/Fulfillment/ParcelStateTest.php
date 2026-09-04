<?php

declare(strict_types=1);

namespace App\Domain\Fulfillment;

use App\Domain\Escrow\LedgerEntryType;
use App\Domain\Money\Money;
use App\Domain\Orders\FulfillmentStatus;
use DateTimeImmutable;

$at = fn (string $when): DateTimeImmutable => new DateTimeImmutable($when);

it('gives a parcel still in the studio the clock the buyer is watching', function () use ($at): void {
    $state = new ParcelState(
        status: FulfillmentStatus::AwaitingShipment,
        placedAt: $at('2026-09-02 09:00:00'),
        now: $at('2026-09-04 09:00:00'),
    );

    expect($state->line())->toBe('Placed 2 days ago · ship by Sep 5');
});

it('reads the last step and its moment once one is behind the parcel', function () use ($at): void {
    $state = new ParcelState(
        status: FulfillmentStatus::AwaitingShipment,
        placedAt: $at('2026-09-02 09:00:00'),
        now: $at('2026-09-04 09:00:00'),
        lastStepLabel: 'Label printed',
        lastStepAt: $at('2026-09-04 06:00:00'),
    );

    expect($state->line())->toBe('Label printed 3 hours ago · waiting for the parcel to leave');
});

it('names the carrier and the day a parcel left', function () use ($at): void {
    $state = new ParcelState(
        status: FulfillmentStatus::Shipped,
        placedAt: $at('2026-08-29 09:00:00'),
        now: $at('2026-09-04 09:00:00'),
        carrier: 'Owl Post',
        shippedAt: $at('2026-09-01 14:30:00'),
    );

    expect($state->line())->toBe('In transit with Owl Post since Sep 1');
});

it('falls back to the carrier a parcel never named', function () use ($at): void {
    $state = new ParcelState(
        status: FulfillmentStatus::Shipped,
        placedAt: $at('2026-08-29 09:00:00'),
        now: $at('2026-09-04 09:00:00'),
    );

    expect($state->line())->toBe('In transit with the carrier');
});

it('says where the money went once the parcel is settled', function (FulfillmentStatus $status, LedgerEntryType $escrow, string $line) use ($at): void {
    $state = new ParcelState(
        status: $status,
        placedAt: $at('2026-08-20 09:00:00'),
        now: $at('2026-09-04 09:00:00'),
        settledAt: $at('2026-08-28 11:00:00'),
        settledAmount: Money::fromCents(61200),
        escrow: $escrow,
    );

    expect($state->line())->toBe($line);
})->with([
    'delivered, released' => [FulfillmentStatus::Delivered, LedgerEntryType::Released, 'Delivered Aug 28 · $612.00 released to your balance'],
    'delivered, paid out' => [FulfillmentStatus::Delivered, LedgerEntryType::PaidOut, 'Delivered Aug 28 · $612.00 paid out'],
    'delivered, still held' => [FulfillmentStatus::Delivered, LedgerEntryType::Held, 'Delivered Aug 28 · $612.00 held in escrow'],
    'declined' => [FulfillmentStatus::Declined, LedgerEntryType::Refunded, 'Declined Aug 28 · $612.00 returned to the buyer'],
    'refunded' => [FulfillmentStatus::Refunded, LedgerEntryType::Refunded, 'Refunded Aug 28 · $612.00 returned to the buyer'],
]);

it('says only what it knows about a settled parcel with no movement behind it', function () use ($at): void {
    $state = new ParcelState(
        status: FulfillmentStatus::Delivered,
        placedAt: $at('2026-08-20 09:00:00'),
        now: $at('2026-09-04 09:00:00'),
        settledAt: $at('2026-08-28 11:00:00'),
    );

    expect($state->line())->toBe('Delivered Aug 28');
});

it('drops the day a settled parcel never recorded', function () use ($at): void {
    $state = new ParcelState(
        status: FulfillmentStatus::Declined,
        placedAt: $at('2026-08-20 09:00:00'),
        now: $at('2026-09-04 09:00:00'),
    );

    expect($state->line())->toBe('Declined');
});

it('counts the elapsed time in the largest unit that fits', function (string $placedAt, string $expected) use ($at): void {
    $state = new ParcelState(
        status: FulfillmentStatus::AwaitingShipment,
        placedAt: $at($placedAt),
        now: $at('2026-09-04 09:00:00'),
    );

    expect($state->line())->toStartWith("Placed {$expected} · ");
})->with([
    'this minute' => ['2026-09-04 08:59:30', 'just now'],
    'one minute' => ['2026-09-04 08:59:00', '1 minute ago'],
    'minutes' => ['2026-09-04 08:20:00', '40 minutes ago'],
    'one hour' => ['2026-09-04 08:00:00', '1 hour ago'],
    'hours' => ['2026-09-04 03:00:00', '6 hours ago'],
    'one day' => ['2026-09-03 09:00:00', '1 day ago'],
    'days' => ['2026-08-30 09:00:00', '5 days ago'],
]);
