<?php

declare(strict_types=1);

namespace App\Domain\Reports;

use App\Domain\Listings\ListingEventType;

it('carries a type and its count, and labels itself by the type', function (): void {
    $count = ListingEventCount::of(ListingEventType::CartAdd, 6);

    expect($count->type)->toBe(ListingEventType::CartAdd)
        ->and($count->count)->toBe(6)
        ->and($count->label())->toBe('Cart add');
});
