<?php

declare(strict_types=1);

namespace App\Domain\Seller;

it('names the column it is sorted by', function (SortableColumn $column, SortableColumn $other): void {
    $sort = TableSort::of($column, SortDirection::Asc);

    expect($sort->isColumn($column))->toBeTrue()
        ->and($sort->isColumn($other))->toBeFalse();
})->with([
    'listings' => [ListingSortColumn::Price, ListingSortColumn::Views],
    'customers' => [CustomerSortColumn::Orders, CustomerSortColumn::Spent],
]);

it('carries an aria-sort value on the sorted column alone', function (SortableColumn $column, SortableColumn $other): void {
    $sort = TableSort::of($column, SortDirection::Asc);

    expect($sort->ariaSort($column))->toBe('ascending')
        ->and($sort->ariaSort($other))->toBeNull();
})->with([
    'listings' => [ListingSortColumn::Price, ListingSortColumn::Views],
    'customers' => [CustomerSortColumn::Orders, CustomerSortColumn::Spent],
]);

it('flips the direction a click on the sorted column would produce', function (SortableColumn $column): void {
    $sort = TableSort::of($column, SortDirection::Desc);

    expect($sort->nextDirectionFor($column))->toBe(SortDirection::Asc);
})->with([
    'listings' => [ListingSortColumn::Price],
    'customers' => [CustomerSortColumn::Orders],
]);

it('defaults a click on a different column to descending', function (SortableColumn $column, SortableColumn $other): void {
    $sort = TableSort::of($column, SortDirection::Asc);

    expect($sort->nextDirectionFor($other))->toBe(SortDirection::Desc);
})->with([
    'listings' => [ListingSortColumn::Price, ListingSortColumn::Views],
    'customers' => [CustomerSortColumn::Orders, CustomerSortColumn::Spent],
]);
