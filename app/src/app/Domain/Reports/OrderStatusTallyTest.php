<?php

declare(strict_types=1);

namespace App\Domain\Reports;

use App\Domain\Orders\OrderStatus;

it('returns every status in lifecycle order, including one nothing counted', function (): void {
    $tally = OrderStatusTally::from([OrderStatus::Paid->value => 3]);

    expect(array_map(fn (OrderStatusCount $row): OrderStatus => $row->status, $tally))
        ->toBe(OrderStatus::cases());
    expect($tally[2]->status)->toBe(OrderStatus::Paid)
        ->and($tally[2]->count)->toBe(3)
        ->and($tally[7]->status)->toBe(OrderStatus::Cancelled)
        ->and($tally[7]->count)->toBe(0);
});
