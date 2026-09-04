<?php

declare(strict_types=1);

namespace App\Domain\Seller;

it('flips between ascending and descending', function (): void {
    expect(SortDirection::Asc->flipped())->toBe(SortDirection::Desc)
        ->and(SortDirection::Desc->flipped())->toBe(SortDirection::Asc);
});

it('reads whether it is ascending', function (): void {
    expect(SortDirection::Asc->isAscending())->toBeTrue()
        ->and(SortDirection::Desc->isAscending())->toBeFalse();
});

it('spells its aria-sort value', function (SortDirection $direction, string $expected): void {
    expect($direction->ariaSort())->toBe($expected);
})->with([
    [SortDirection::Asc, 'ascending'],
    [SortDirection::Desc, 'descending'],
]);
