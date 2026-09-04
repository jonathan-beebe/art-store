<?php

declare(strict_types=1);

namespace App\Domain\Seller;

it('opens on what each buyer has spent, largest first', function (): void {
    $sort = CustomerSort::default();

    expect($sort->column)->toBe(CustomerSortColumn::Spent)
        ->and($sort->direction)->toBe(SortDirection::Desc);
});

it('reads whether a column is the sorted one', function (): void {
    $sort = CustomerSort::of(CustomerSortColumn::Orders, SortDirection::Asc);

    expect($sort->isColumn(CustomerSortColumn::Orders))->toBeTrue()
        ->and($sort->isColumn(CustomerSortColumn::Spent))->toBeFalse();
});

it('carries aria-sort on the sorted column alone', function (): void {
    $sort = CustomerSort::of(CustomerSortColumn::Orders, SortDirection::Asc);

    expect($sort->ariaSort(CustomerSortColumn::Orders))->toBe('ascending')
        ->and($sort->ariaSort(CustomerSortColumn::Spent))->toBeNull();
});

it('flips the direction of the sorted column and sorts every other one descending', function (): void {
    $sort = CustomerSort::of(CustomerSortColumn::Orders, SortDirection::Desc);

    expect($sort->nextDirectionFor(CustomerSortColumn::Orders))->toBe(SortDirection::Asc)
        ->and($sort->nextDirectionFor(CustomerSortColumn::Name))->toBe(SortDirection::Desc);
});
