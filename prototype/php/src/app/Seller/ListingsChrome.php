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
     * @param  list<NavLink>  $viewLinks
     * @param  list<string>  $viewIcons  one `<path d="">` per entry of `viewLinks`, in the same order
     * @param  TableSort<ListingTableRow>  $sort
     * @param  list<ListingSortColumn>  $sortOptions
     * @param  list<ColumnHeader>  $columnHeaders
     * @param  array<string, string>  $sortFormFields
     */
    private function __construct(
        public ListingView $view,
        public array $viewLinks,
        public array $viewIcons,
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
            viewLinks: NavLinks::for(
                routeName: 'seller.listings.index',
                without: collect($roundTripped)->except('view')->all(),
                param: 'view',
                cases: ListingView::cases(),
                label: fn (ListingView $case): string => $case->label(),
                value: fn (ListingView $case): string => $case->value,
                active: fn (ListingView $case): bool => $case === $view,
            ),
            viewIcons: array_map(fn (ListingView $case): string => $case->iconPath(), ListingView::cases()),
            sort: $sort,
            sortOptions: self::sortOptions(),
            columnHeaders: ColumnHeaders::for('seller.listings.index', $roundTripped, $sort, ListingSortColumn::cases()),
            sortFormFields: collect($roundTripped)->except(['sort', 'dir'])->all(),
        );
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
}
