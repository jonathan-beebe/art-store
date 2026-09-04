<?php

declare(strict_types=1);

namespace App\Domain\Seller;

it('defaults to views, descending', function (): void {
    $sort = ListingSort::default();

    expect($sort->column)->toBe(ListingSortColumn::Views)
        ->and($sort->direction)->toBe(SortDirection::Desc);
});

it('names the column it is sorted by', function (): void {
    $sort = ListingSort::of(ListingSortColumn::Price, SortDirection::Asc);

    expect($sort->isColumn(ListingSortColumn::Price))->toBeTrue()
        ->and($sort->isColumn(ListingSortColumn::Views))->toBeFalse();
});

it('carries an aria-sort value on the sorted column alone', function (): void {
    $sort = ListingSort::of(ListingSortColumn::Price, SortDirection::Asc);

    expect($sort->ariaSort(ListingSortColumn::Price))->toBe('ascending')
        ->and($sort->ariaSort(ListingSortColumn::Views))->toBeNull();
});

it('flips the direction a click on the sorted column would produce', function (): void {
    $sort = ListingSort::of(ListingSortColumn::Price, SortDirection::Desc);

    expect($sort->nextDirectionFor(ListingSortColumn::Price))->toBe(SortDirection::Asc);
});

it('defaults a click on a different column to descending', function (): void {
    $sort = ListingSort::of(ListingSortColumn::Price, SortDirection::Asc);

    expect($sort->nextDirectionFor(ListingSortColumn::Views))->toBe(SortDirection::Desc);
});
