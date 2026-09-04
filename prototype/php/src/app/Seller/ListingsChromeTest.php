<?php

declare(strict_types=1);

namespace App\Seller;

use App\Domain\Seller\ListingSort;
use App\Domain\Seller\ListingSortColumn;
use App\Domain\Seller\ListingSortDirection;
use App\Domain\Seller\ListingView;
use RuntimeException;

/**
 * @param  list<ViewLink>  $links
 */
function viewLinkFor(array $links, ListingView $view): ViewLink
{
    foreach ($links as $link) {
        if ($link->view === $view) {
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
    $chrome = ListingsChrome::build([], ListingView::Table, ListingSort::default());

    expect($chrome->view)->toBe(ListingView::Table)
        ->and($chrome->sort)->toEqual(ListingSort::default());
});

it('builds one view link per view, marking the current one active', function (): void {
    $chrome = ListingsChrome::build(['range' => '7'], ListingView::Table, ListingSort::default());

    expect($chrome->viewLinks)->toHaveCount(3);

    $table = viewLinkFor($chrome->viewLinks, ListingView::Table);
    $grid = viewLinkFor($chrome->viewLinks, ListingView::Grid);

    expect($table->active)->toBeTrue()
        ->and($grid->active)->toBeFalse()
        ->and($table->href)->toContain('range=7')
        ->and($table->href)->toContain('view=table');
});

it('offers every sort column but status in the select', function (): void {
    $chrome = ListingsChrome::build([], ListingView::Table, ListingSort::default());

    expect($chrome->sortOptions)->toHaveCount(10)
        ->and($chrome->sortOptions)->not->toContain(ListingSortColumn::Status);
});

it('builds one column header per column, carrying the flipped direction', function (): void {
    $sort = ListingSort::of(ListingSortColumn::Price, ListingSortDirection::Asc);
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
    $chrome = ListingsChrome::build(['sort' => 'price', 'dir' => 'asc', 'range' => '7'], ListingView::Table, ListingSort::default());

    expect($chrome->sortFormFields)->toBe(['range' => '7']);
});
