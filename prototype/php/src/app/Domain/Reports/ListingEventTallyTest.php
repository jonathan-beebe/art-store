<?php

declare(strict_types=1);

namespace App\Domain\Reports;

use App\Domain\Analytics\AnalyticsEventName;

it('returns every event name in declared order, including one nothing counted', function (): void {
    $tally = ListingEventTally::from([AnalyticsEventName::ListingView->value => 12]);

    expect(array_map(fn (ListingEventCount $row): AnalyticsEventName => $row->name, $tally))
        ->toBe(AnalyticsEventName::cases());
    expect($tally[0]->name)->toBe(AnalyticsEventName::ListingView)
        ->and($tally[0]->count)->toBe(12)
        ->and($tally[1]->name)->toBe(AnalyticsEventName::ListingFavorite)
        ->and($tally[1]->count)->toBe(0);
});
