<?php

declare(strict_types=1);

namespace App\Seller;

use App\Domain\Seller\ListingSortColumn;
use App\Domain\Seller\SortDirection;
use App\Domain\Seller\TableSort;

it('builds one header per column, in the given order', function (): void {
    $sort = TableSort::of(ListingSortColumn::Price, SortDirection::Asc);

    $headers = ColumnHeaders::for('seller.listings.index', [], $sort, [ListingSortColumn::Price, ListingSortColumn::Views]);

    expect($headers)->toHaveCount(2)
        ->and($headers[0]->column)->toBe(ListingSortColumn::Price)
        ->and($headers[1]->column)->toBe(ListingSortColumn::Views);
});

it('marks the sorted column alone with an aria-sort value', function (): void {
    $sort = TableSort::of(ListingSortColumn::Price, SortDirection::Asc);

    $headers = ColumnHeaders::for('seller.listings.index', [], $sort, [ListingSortColumn::Price, ListingSortColumn::Views]);

    expect($headers[0]->ariaSort)->toBe('ascending')
        ->and($headers[1]->ariaSort)->toBe('none');
});

it('carries the flipped direction on the sorted column and descending on every other', function (): void {
    $sort = TableSort::of(ListingSortColumn::Price, SortDirection::Asc);

    $headers = ColumnHeaders::for('seller.listings.index', [], $sort, [ListingSortColumn::Price, ListingSortColumn::Views]);

    expect($headers[0]->href)->toContain('dir=desc')
        ->and($headers[1]->href)->toContain('dir=desc');
});

it('drops sort and dir from the round-tripped filters a header link carries', function (): void {
    $sort = ListingSortColumn::defaultSort();

    $headers = ColumnHeaders::for('seller.listings.index', ['sort' => 'price', 'dir' => 'asc', 'range' => '7'], $sort, [ListingSortColumn::Views]);

    expect($headers[0]->href)->toContain('range=7')
        ->and($headers[0]->href)->toContain('sort=views');
});
