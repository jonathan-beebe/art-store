<?php

declare(strict_types=1);

namespace App\Domain\Reports;

use App\Domain\Listings\ListingEventType;

it('returns every event type in declared order, including one nothing counted', function (): void {
    $tally = ListingEventTally::from([ListingEventType::View->value => 12]);

    expect(array_map(fn (ListingEventCount $row): ListingEventType => $row->type, $tally))
        ->toBe(ListingEventType::cases());
    expect($tally[0]->type)->toBe(ListingEventType::View)
        ->and($tally[0]->count)->toBe(12)
        ->and($tally[1]->type)->toBe(ListingEventType::Favorite)
        ->and($tally[1]->count)->toBe(0);
});
