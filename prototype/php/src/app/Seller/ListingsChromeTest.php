<?php

declare(strict_types=1);

namespace App\Seller;

use App\Domain\Seller\ListingSortColumn;
use App\Domain\Seller\ListingView;
use App\Domain\Seller\SortDirection;
use App\Domain\Seller\TableSort;
use RuntimeException;

/**
 * @param  list<NavLink>  $links
 */
function viewLinkFor(array $links, ListingView $view): NavLink
{
    foreach ($links as $link) {
        if ($link->label === $view->label()) {
            return $link;
        }
    }

    throw new RuntimeException("no link for {$view->value}");
}

/**
 * @param  list<ColumnHeader>  $headers
 */
function columnHeaderFor(array $headers, ListingSortColumn $column): ColumnHeader
{
    foreach ($headers as $header) {
        if ($header->column === $column) {
            return $header;
        }
    }

    throw new RuntimeException("no header for {$column->value}");
}

it('names the current view and sort', function (): void {
    $chrome = ListingsChrome::build([], ListingView::Table, TableSort::of(ListingSortColumn::Views, SortDirection::Desc));

    expect($chrome->view)->toBe(ListingView::Table)
        ->and($chrome->sort)->toEqual(TableSort::of(ListingSortColumn::Views, SortDirection::Desc));
});

it('builds one view link per view, marking the current one active', function (): void {
    $chrome = ListingsChrome::build(['range' => '7'], ListingView::Table, TableSort::of(ListingSortColumn::Views, SortDirection::Desc));

    expect($chrome->viewLinks)->toHaveCount(3);

    $table = viewLinkFor($chrome->viewLinks, ListingView::Table);
    $grid = viewLinkFor($chrome->viewLinks, ListingView::Grid);

    expect($table->active)->toBeTrue()
        ->and($grid->active)->toBeFalse()
        ->and($table->href)->toContain('range=7')
        ->and($table->href)->toContain('view=table');
});

it('carries each view link\'s own icon', function (): void {
    $chrome = ListingsChrome::build([], ListingView::Table, TableSort::of(ListingSortColumn::Views, SortDirection::Desc));

    $table = viewLinkFor($chrome->viewLinks, ListingView::Table);

    expect($table->iconPath)->toBe(ListingView::Table->iconPath());
});

it('offers every sort column but status in the select', function (): void {
    $chrome = ListingsChrome::build([], ListingView::Table, TableSort::of(ListingSortColumn::Views, SortDirection::Desc));

    expect($chrome->sortOptions)->toHaveCount(10)
        ->and($chrome->sortOptions)->not->toContain(ListingSortColumn::Status);
});

it('builds one column header per column, carrying the flipped direction', function (): void {
    $sort = TableSort::of(ListingSortColumn::Price, SortDirection::Asc);
    $chrome = ListingsChrome::build([], ListingView::Table, $sort);

    expect($chrome->columnHeaders)->toHaveCount(11);

    $price = columnHeaderFor($chrome->columnHeaders, ListingSortColumn::Price);
    $views = columnHeaderFor($chrome->columnHeaders, ListingSortColumn::Views);

    expect($price->ariaSort)->toBe('ascending')
        ->and($price->href)->toContain('dir=desc')
        ->and($views->ariaSort)->toBe('none')
        ->and($views->href)->toContain('dir=desc');
});

it('drops sort and dir from the sort forms hidden fields', function (): void {
    $chrome = ListingsChrome::build(['sort' => 'price', 'dir' => 'asc', 'range' => '7'], ListingView::Table, TableSort::of(ListingSortColumn::Views, SortDirection::Desc));

    expect($chrome->sortFormFields)->toBe(['range' => '7']);
});
