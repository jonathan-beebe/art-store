<?php

declare(strict_types=1);

namespace App\Domain\Customers;

it('folds cart lines', function (
    array $verifiedCartLines,
    array $anonymousCartLines,
    array $stockByListing,
    array $expected,
): void {
    /**
     * @var list<CustomerCartLine> $verifiedCartLines
     * @var list<CustomerCartLine> $anonymousCartLines
     * @var array<string, int> $stockByListing
     */
    $plan = CustomerMergePlan::for($verifiedCartLines, $anonymousCartLines, [], [], $stockByListing);

    expect(array_map(
        fn (CustomerCartLine $line): array => ['listingId' => $line->listingId, 'quantity' => $line->quantity],
        $plan->cartLines,
    ))->toBe($expected);
})->with([
    'disjoint carts merge to the union' => [
        [new CustomerCartLine('lst_1', 2)],
        [new CustomerCartLine('lst_2', 1)],
        [],
        [['listingId' => 'lst_1', 'quantity' => 2], ['listingId' => 'lst_2', 'quantity' => 1]],
    ],
    'the same listing on both sides sums quantities' => [
        [new CustomerCartLine('lst_1', 2)],
        [new CustomerCartLine('lst_1', 3)],
        [],
        [['listingId' => 'lst_1', 'quantity' => 5]],
    ],
    'a summed quantity above stock clamps to stock' => [
        [new CustomerCartLine('lst_1', 2)],
        [new CustomerCartLine('lst_1', 3)],
        ['lst_1' => 4],
        [['listingId' => 'lst_1', 'quantity' => 4]],
    ],
    'a line already at stock is unaffected' => [
        [new CustomerCartLine('lst_1', 4)],
        [],
        ['lst_1' => 4],
        [['listingId' => 'lst_1', 'quantity' => 4]],
    ],
    'stock of zero drops the line' => [
        [new CustomerCartLine('lst_1', 2)],
        [],
        ['lst_1' => 0],
        [],
    ],
    'a listing missing from the stock map is not clamped' => [
        [new CustomerCartLine('lst_1', 50)],
        [],
        [],
        [['listingId' => 'lst_1', 'quantity' => 50]],
    ],
    'both carts empty gives an empty cart' => [
        [], [], [], [],
    ],
    'one side empty carries the other through untouched' => [
        [new CustomerCartLine('lst_1', 2)],
        [],
        [],
        [['listingId' => 'lst_1', 'quantity' => 2]],
    ],
    'line order is verified listings first, then anonymous-only listings' => [
        [new CustomerCartLine('lst_3', 1), new CustomerCartLine('lst_1', 1)],
        [new CustomerCartLine('lst_4', 1), new CustomerCartLine('lst_2', 1)],
        [],
        [
            ['listingId' => 'lst_3', 'quantity' => 1],
            ['listingId' => 'lst_1', 'quantity' => 1],
            ['listingId' => 'lst_4', 'quantity' => 1],
            ['listingId' => 'lst_2', 'quantity' => 1],
        ],
    ],
]);

it('unions favorites', function (
    array $verifiedFavoriteListingIds,
    array $anonymousFavoriteListingIds,
    array $expectedMove,
    array $expectedDrop,
): void {
    /**
     * @var list<string> $verifiedFavoriteListingIds
     * @var list<string> $anonymousFavoriteListingIds
     */
    $plan = CustomerMergePlan::for([], [], $verifiedFavoriteListingIds, $anonymousFavoriteListingIds, []);

    expect($plan->favoritesToMove)->toBe($expectedMove)
        ->and($plan->favoritesToDrop)->toBe($expectedDrop);
})->with([
    'a favorite the verified customer does not already have moves' => [
        ['lst_1', 'lst_2'], ['lst_2', 'lst_3'], ['lst_3'], ['lst_2'],
    ],
    'anonymous favorites de-duplicate before moving' => [
        [], ['lst_1', 'lst_1', 'lst_2'], ['lst_1', 'lst_2'], [],
    ],
    'a verified customer with no favorites moves every anonymous favorite' => [
        [], ['lst_1', 'lst_2'], ['lst_1', 'lst_2'], [],
    ],
    'an anonymous customer with no favorites moves and drops nothing' => [
        ['lst_1', 'lst_2'], [], [], [],
    ],
    'both empty gives nothing to move or drop' => [
        [], [], [], [],
    ],
    'the same listing on both sides drops rather than moves' => [
        ['lst_1'], ['lst_1'], [], ['lst_1'],
    ],
]);

it('folds an empty merge into an empty plan', function (): void {
    $plan = CustomerMergePlan::for([], [], [], [], []);

    expect($plan->cartLines)->toBe([])
        ->and($plan->favoritesToMove)->toBe([])
        ->and($plan->favoritesToDrop)->toBe([]);
});
