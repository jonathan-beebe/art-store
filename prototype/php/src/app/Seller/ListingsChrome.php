<?php

declare(strict_types=1);

namespace App\Seller;

use App\Domain\Seller\ListingSortColumn;
use App\Domain\Seller\ListingTableRow;
use App\Domain\Seller\ListingView;
use App\Domain\Seller\TableSort;

/**
 * The listings header every view shares: the view switch, and, on table
 * and grid, the sort select and the table's own column headers. Built
 * once per request from the round-tripped filters and the current view
 * and sort, so the controller and the views read one object instead of
 * rebuilding enums out of parallel arrays.
 */
final readonly class ListingsChrome
{
    /**
     * @param  list<ViewLink>  $viewLinks
     * @param  TableSort<ListingTableRow>  $sort
     * @param  list<ListingSortColumn>  $sortOptions
     * @param  list<ColumnHeader>  $columnHeaders
     * @param  array<string, string>  $sortFormFields
     */
    private function __construct(
        public ListingView $view,
        public array $viewLinks,
        public TableSort $sort,
        public array $sortOptions,
        public array $columnHeaders,
        public array $sortFormFields,
    ) {}

    /**
     * @param  array<string, string>  $roundTripped  always names `view`
     *                                               explicitly — the index route's own
     *                                               {@see \App\Http\Requests\Seller\ListingsQueryRequest::roundTripped()},
     *                                               or that plus the view `from` resolved to on the detail route,
     *                                               whose own query carries `from`.
     * @param  TableSort<ListingTableRow>  $sort
     */
    public static function build(array $roundTripped, ListingView $view, TableSort $sort): self
    {
        return new self(
            view: $view,
            viewLinks: self::viewLinks($roundTripped, $view),
            sort: $sort,
            sortOptions: self::sortOptions(),
            columnHeaders: self::columnHeaders($roundTripped, $sort),
            sortFormFields: collect($roundTripped)->except(['sort', 'dir'])->all(),
        );
    }

    /**
     * @param  array<string, string>  $roundTripped
     * @return list<ViewLink>
     */
    private static function viewLinks(array $roundTripped, ListingView $current): array
    {
        $without = collect($roundTripped)->except('view')->all();

        return array_map(fn (ListingView $view): ViewLink => new ViewLink(
            view: $view,
            href: route('seller.listings.index', [...$without, 'view' => $view->value]),
            active: $view === $current,
        ), ListingView::cases());
    }

    /**
     * The header's sort `<select>`: every column but Status, which the
     * table's own header link already sorts.
     *
     * @return list<ListingSortColumn>
     */
    private static function sortOptions(): array
    {
        return array_values(array_filter(
            ListingSortColumn::cases(),
            fn (ListingSortColumn $column): bool => $column !== ListingSortColumn::Status,
        ));
    }

    /**
     * @param  array<string, string>  $roundTripped
     * @param  TableSort<ListingTableRow>  $sort
     * @return list<ColumnHeader>
     */
    private static function columnHeaders(array $roundTripped, TableSort $sort): array
    {
        $without = collect($roundTripped)->except(['sort', 'dir'])->all();

        return array_map(fn (ListingSortColumn $column): ColumnHeader => new ColumnHeader(
            column: $column,
            href: route('seller.listings.index', [...$without, 'sort' => $column->value, 'dir' => $sort->nextDirectionFor($column)->value]),
            ariaSort: $sort->ariaSort($column) ?? 'none',
        ), ListingSortColumn::cases());
    }
}
