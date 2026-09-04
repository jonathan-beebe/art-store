<?php

declare(strict_types=1);

namespace App\Domain\Seller;

it('flips between ascending and descending', function (): void {
    expect(ListingSortDirection::Asc->flipped())->toBe(ListingSortDirection::Desc)
        ->and(ListingSortDirection::Desc->flipped())->toBe(ListingSortDirection::Asc);
});

it('reads whether it is ascending', function (): void {
    expect(ListingSortDirection::Asc->isAscending())->toBeTrue()
        ->and(ListingSortDirection::Desc->isAscending())->toBeFalse();
});

it('spells its aria-sort value', function (ListingSortDirection $direction, string $expected): void {
    expect($direction->ariaSort())->toBe($expected);
})->with([
    [ListingSortDirection::Asc, 'ascending'],
    [ListingSortDirection::Desc, 'descending'],
]);
