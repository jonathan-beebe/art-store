<?php

declare(strict_types=1);

namespace App\Http\Requests\Seller;

use App\Domain\Analytics\AnalyticsRange;
use App\Domain\Seller\ListingSortColumn;
use App\Domain\Seller\ListingTableRow;
use App\Domain\Seller\ListingView;
use App\Domain\Seller\SortDirection;
use App\Domain\Seller\TableSort;
use Illuminate\Validation\Rule;

/**
 * The listings tool's query vocabulary, shared by `GET /seller/listings`
 * (`view`, `sort`, `dir`, `range`) and `GET /seller/listings/{listing}`
 * (`from`, `sort`, `dir`, `range`). `from` names the view a detail row was
 * opened from: absent for the list pane's own detail, `table` or `grid`
 * for the overlay/takeover.
 */
final class ListingsQueryRequest extends SellerQueryRequest
{
    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'view' => ['nullable', Rule::enum(ListingView::class)],
            'from' => ['nullable', Rule::in(array_map(fn (ListingView $view): string => $view->value, ListingView::openable()))],
            'sort' => ['nullable', Rule::enum(ListingSortColumn::class)],
            'dir' => ['nullable', Rule::enum(SortDirection::class)],
            'range' => ['nullable', Rule::in(array_map(strval(...), AnalyticsRange::SIZES))],
        ];
    }

    public function view(): ListingView
    {
        return $this->enum('view', ListingView::class) ?? ListingView::default();
    }

    public function from(): ?ListingView
    {
        return $this->enum('from', ListingView::class);
    }

    /**
     * Any `sort` or `dir` in the query sets the sort; neither present keeps the default.
     *
     * @return TableSort<ListingTableRow>
     */
    public function sort(): TableSort
    {
        $column = $this->enum('sort', ListingSortColumn::class);
        $direction = $this->enum('dir', SortDirection::class);
        $default = ListingSortColumn::defaultSort();

        return TableSort::of($column ?? $default->column, $direction ?? $default->direction);
    }

    /**
     * The submitted filters, in the shape every view/sort/range link round-trips.
     *
     * @return array<string, string>
     */
    public function roundTripped(): array
    {
        return $this->roundTrippedOf(['view', 'sort', 'dir', 'range']);
    }
}
