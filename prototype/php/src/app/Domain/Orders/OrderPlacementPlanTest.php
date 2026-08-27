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

it('looks a blocked line up by its own line id, for two configurations of the same listing', function (): void {
    $blocked = new PlaceableLine(
        listingId: 'lst_00000000000000000000000001',
        title: 'Engraved Signet Ring',
        status: ListingStatus::ForSale,
        availableQuantity: 1,
        quantity: 1,
        hasActiveRemoval: false,
        lineId: 'cti_00000000000000000000000001',
        configured: true,
        variantEnabled: false,
    );
    $placeable = new PlaceableLine(
        listingId: 'lst_00000000000000000000000001',
        title: 'Engraved Signet Ring',
        status: ListingStatus::ForSale,
        availableQuantity: 1,
        quantity: 1,
        hasActiveRemoval: false,
        lineId: 'cti_00000000000000000000000002',
        configured: true,
        variantEnabled: true,
    );

    $plan = OrderPlacementPlan::for([$blocked, $placeable]);

    expect($plan->blockedReasonFor('cti_00000000000000000000000001'))->toBe(UnavailableReason::OffSale)
        ->and($plan->blockedReasonFor('cti_00000000000000000000000002'))->toBeNull();
});

it('judges a configured line off its variant and unit rather than the listing quantity', function (
    PlaceableLine $line,
    ?UnavailableReason $reason,
): void {
    $plan = OrderPlacementPlan::for([$line]);

    expect($plan->isPlaceable())->toBe($reason === null)
        ->and($plan->blockedReasonFor($line->lineId ?? ''))->toBe($reason);
})->with([
    'an enabled, uncapped variant is always placeable' => [
        new PlaceableLine('lst_1', 'Ring', ListingStatus::ForSale, 0, 1, false, 'cti_1', configured: true, variantEnabled: true),
        null,
    ],
    'a disabled variant is off sale' => [
        new PlaceableLine('lst_1', 'Ring', ListingStatus::ForSale, 0, 1, false, 'cti_1', configured: true, variantEnabled: false),
        UnavailableReason::OffSale,
    ],
    'a serialized line whose unit is still available is placeable' => [
        new PlaceableLine('lst_1', 'Candlestick', ListingStatus::ForSale, 0, 1, false, 'cti_1', configured: true, variantEnabled: true, serialized: true, unitAvailable: true),
        null,
    ],
    'a serialized line whose unit sold to someone else is sold out' => [
        new PlaceableLine('lst_1', 'Candlestick', ListingStatus::ForSale, 0, 1, false, 'cti_1', configured: true, variantEnabled: true, serialized: true, unitAvailable: false),
        UnavailableReason::SoldOut,
    ],
    'a non-serialized variant with stock left is placeable' => [
        new PlaceableLine('lst_1', 'Tee', ListingStatus::ForSale, 0, 2, false, 'cti_1', configured: true, variantEnabled: true, variantRemainingQuantity: 3),
        null,
    ],
    'a non-serialized variant with nothing left is sold out' => [
        new PlaceableLine('lst_1', 'Tee', ListingStatus::ForSale, 0, 1, false, 'cti_1', configured: true, variantEnabled: true, variantRemainingQuantity: 0),
        UnavailableReason::SoldOut,
    ],
    'a non-serialized variant asked for more than remains is short stock' => [
        new PlaceableLine('lst_1', 'Tee', ListingStatus::ForSale, 0, 5, false, 'cti_1', configured: true, variantEnabled: true, variantRemainingQuantity: 2),
        UnavailableReason::ShortStock,
    ],
    'a removal outranks the variant standing behind it' => [
        new PlaceableLine('lst_1', 'Ring', ListingStatus::ForSale, 0, 1, true, 'cti_1', configured: true, variantEnabled: true),
        UnavailableReason::Removed,
    ],
    'the listing status outranks the variant standing behind it' => [
        new PlaceableLine('lst_1', 'Ring', ListingStatus::Archived, 0, 1, false, 'cti_1', configured: true, variantEnabled: true),
        UnavailableReason::OffSale,
    ],
]);
