<?php

declare(strict_types=1);

namespace App\Domain\Reports;

use App\Domain\Listings\ListingStatus;
use App\Domain\Orders\FulfillmentStatus;
use App\Domain\Orders\OrderStatus;
use BackedEnum;

it('reads a single-word status as a sentence', function (): void {
    expect(StatusLabel::of(ListingStatus::Draft))->toBe('Draft');
});

it('replaces the underscores of a multi-word status', function (BackedEnum $status, string $expected): void {
    expect(StatusLabel::of($status))->toBe($expected);
})->with([
    'for sale' => [ListingStatus::ForSale, 'For sale'],
    'awaiting shipment' => [FulfillmentStatus::AwaitingShipment, 'Awaiting shipment'],
    'pending verification' => [OrderStatus::PendingVerification, 'Pending verification'],
]);
