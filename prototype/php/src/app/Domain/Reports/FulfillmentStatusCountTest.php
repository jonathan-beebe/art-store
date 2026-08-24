<?php

declare(strict_types=1);

namespace App\Domain\Reports;

use App\Domain\Orders\FulfillmentStatus;

it('carries a status and its count, and labels itself by the status', function (): void {
    $count = FulfillmentStatusCount::of(FulfillmentStatus::AwaitingShipment, 4);

    expect($count->status)->toBe(FulfillmentStatus::AwaitingShipment)
        ->and($count->count)->toBe(4)
        ->and($count->label())->toBe('Awaiting shipment');
});
