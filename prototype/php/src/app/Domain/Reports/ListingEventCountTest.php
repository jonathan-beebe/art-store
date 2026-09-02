<?php

declare(strict_types=1);

namespace App\Domain\Reports;

use App\Domain\Analytics\AnalyticsEventName;

it('carries a name and its count, and labels itself by the name', function (): void {
    $count = ListingEventCount::of(AnalyticsEventName::ListingCartAdd, 6);

    expect($count->name)->toBe(AnalyticsEventName::ListingCartAdd)
        ->and($count->count)->toBe(6)
        ->and($count->label())->toBe('Cart add');
});
