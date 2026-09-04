<?php

declare(strict_types=1);

namespace App\Domain\Reports;

use App\Domain\Listings\ListingStatus;

it('carries a status and its count, and labels itself by the status', function (): void {
    $count = ListingStatusCount::of(ListingStatus::ForSale, 3);

    expect($count->status)->toBe(ListingStatus::ForSale)
        ->and($count->count)->toBe(3)
        ->and($count->label())->toBe('For sale');
});
