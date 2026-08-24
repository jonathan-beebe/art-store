<?php

declare(strict_types=1);

namespace App\Domain\Reports;

use App\Domain\Orders\FulfillmentStatus;

it('returns every status in lifecycle order, including one nothing counted', function (): void {
    $tally = FulfillmentStatusTally::from([FulfillmentStatus::Shipped->value => 5]);

    expect(array_map(fn (FulfillmentStatusCount $row): FulfillmentStatus => $row->status, $tally))
        ->toBe(FulfillmentStatus::cases());
    expect($tally[1]->status)->toBe(FulfillmentStatus::Shipped)
        ->and($tally[1]->count)->toBe(5)
        ->and($tally[3]->status)->toBe(FulfillmentStatus::Declined)
        ->and($tally[3]->count)->toBe(0);
});
