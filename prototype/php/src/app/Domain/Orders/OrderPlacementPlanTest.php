<?php

declare(strict_types=1);

namespace App\Domain\Orders;

use App\Domain\Listings\ListingStatus;

function placeableLine(
    string $listingId = 'lst_00000000000000000000000001',
    string $title = 'Harbour at Dusk',
    ListingStatus $status = ListingStatus::ForSale,
    int $availableQuantity = 1,
    int $quantity = 1,
    bool $hasActiveRemoval = false,
): PlaceableLine {
    return new PlaceableLine($listingId, $title, $status, $availableQuantity, $quantity, $hasActiveRemoval);
}

it('has nothing standing in the way of a cart of listings still for sale', function (): void {
    $lines = [placeableLine(), placeableLine(listingId: 'lst_00000000000000000000000002', title: 'Low Tide')];

    $plan = OrderPlacementPlan::for($lines);

    expect($plan->isPlaceable())->toBeTrue()
        ->and($plan->lines)->toBe($lines)
        ->and($plan->blocked)->toBe([]);
});

it('has nothing standing in the way of an empty cart', function (): void {
    $plan = OrderPlacementPlan::for([]);

    expect($plan->isPlaceable())->toBeTrue()
        ->and($plan->blocked)->toBe([]);
});

it('names why a line stands in the way', function (PlaceableLine $line, UnavailableReason $reason): void {
    $plan = OrderPlacementPlan::for([$line]);

    expect($plan->isPlaceable())->toBeFalse()
        ->and($plan->blocked)->toHaveCount(1)
        ->and($plan->blocked[0]->listingId)->toBe('lst_00000000000000000000000001')
        ->and($plan->blocked[0]->title)->toBe('Harbour at Dusk')
        ->and($plan->blocked[0]->reason)->toBe($reason);
})->with([
    'a listing an admin removed' => [placeableLine(hasActiveRemoval: true), UnavailableReason::Removed],
    'a listing another buyer took' => [placeableLine(status: ListingStatus::Sold, availableQuantity: 0), UnavailableReason::SoldOut],
    'a listing the seller archived' => [placeableLine(status: ListingStatus::Archived), UnavailableReason::OffSale],
    'a listing back to draft' => [placeableLine(status: ListingStatus::Draft), UnavailableReason::OffSale],
    'a cart asking for more than is left' => [placeableLine(availableQuantity: 1, quantity: 2), UnavailableReason::ShortStock],
    'nothing left to sell reads as sold out rather than short of stock' => [placeableLine(availableQuantity: 0, quantity: 2), UnavailableReason::SoldOut],
    'a removal outranks whatever the listing status says' => [placeableLine(status: ListingStatus::Sold, hasActiveRemoval: true), UnavailableReason::Removed],
]);

it('names every line standing in the way, not just the first', function (): void {
    $lines = [
        placeableLine(listingId: 'lst_00000000000000000000000007', title: 'Low Tide', status: ListingStatus::Sold, availableQuantity: 0),
        placeableLine(listingId: 'lst_00000000000000000000000008', title: 'Harbour at Dusk'),
        placeableLine(listingId: 'lst_00000000000000000000000009', title: 'Long Shore', hasActiveRemoval: true),
    ];

    $plan = OrderPlacementPlan::for($lines);

    expect($plan->isPlaceable())->toBeFalse()
        ->and(array_map(fn (BlockedLine $line): string => $line->listingId, $plan->blocked))
        ->toBe(['lst_00000000000000000000000007', 'lst_00000000000000000000000009']);
});

it('looks a blocked line up by listing id, for a page that marks it', function (): void {
    $plan = OrderPlacementPlan::for([
        placeableLine(listingId: 'lst_00000000000000000000000001', status: ListingStatus::Archived),
        placeableLine(listingId: 'lst_00000000000000000000000002'),
    ]);

    expect($plan->blockedReasonFor('lst_00000000000000000000000001'))->toBe(UnavailableReason::OffSale)
        ->and($plan->blockedReasonFor('lst_00000000000000000000000002'))->toBeNull()
        ->and($plan->blockedReasonFor('lst_00000000000000000000000099'))->toBeNull();
});
