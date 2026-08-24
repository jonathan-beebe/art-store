<?php

declare(strict_types=1);

namespace App\Domain\Orders;

use App\Domain\Listings\ListingStatus;

it('holds what a line asks for beside what the listing behind it allows', function (): void {
    $line = new PlaceableLine(
        listingId: 'lst_00000000000000000000000001',
        title: 'Harbour at Dusk',
        status: ListingStatus::ForSale,
        availableQuantity: 3,
        quantity: 1,
        hasActiveRemoval: false,
    );

    expect($line->listingId)->toBe('lst_00000000000000000000000001')
        ->and($line->title)->toBe('Harbour at Dusk')
        ->and($line->status)->toBe(ListingStatus::ForSale)
        ->and($line->availableQuantity)->toBe(3)
        ->and($line->quantity)->toBe(1)
        ->and($line->hasActiveRemoval)->toBeFalse();
});
