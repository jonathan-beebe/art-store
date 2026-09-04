<?php

declare(strict_types=1);

namespace App\Domain\Reports;

use App\Domain\Listings\ListingStatus;

it('returns every status in lifecycle order', function (): void {
    $tally = ListingStatusTally::from([]);

    expect(array_map(fn (ListingStatusCount $row): ListingStatus => $row->status, $tally))
        ->toBe([ListingStatus::Draft, ListingStatus::ForSale, ListingStatus::Sold, ListingStatus::Archived]);
});

it('reads the count recorded for a status', function (): void {
    $tally = ListingStatusTally::from([ListingStatus::ForSale->value => 3]);

    expect($tally[1]->count)->toBe(3);
});

it('counts a status with no listings as zero', function (): void {
    $tally = ListingStatusTally::from([ListingStatus::ForSale->value => 3]);

    expect($tally[0]->count)->toBe(0);
});

it('totals every status', function (): void {
    $tally = ListingStatusTally::from([
        ListingStatus::ForSale->value => 3,
        ListingStatus::Sold->value => 2,
    ]);

    expect(ListingStatusTally::total($tally))->toBe(5);
});
