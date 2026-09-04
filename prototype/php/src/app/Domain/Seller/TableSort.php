<?php

declare(strict_types=1);

namespace App\Domain\Seller;

/**
 * A seller table's current sort: one column, one direction. The listings
 * table and the customers table each name their own default column at the
 * call site that opens without a `sort` in the query.
 *
 * @template TRow of object
 */
final readonly class TableSort
{
    /**
     * @param  SortableColumn<TRow>  $column
     */
    private function __construct(
        public SortableColumn $column,
        public SortDirection $direction,
    ) {}

    /**
     * @template TGivenRow of object
     *
     * @param  SortableColumn<TGivenRow>  $column
     * @return self<TGivenRow>
     */
    public static function of(SortableColumn $column, SortDirection $direction): self
    {
        /** @var self<TGivenRow> $sort */
        $sort = new self($column, $direction);

        return $sort;
    }

    /**
     * @param  SortableColumn<TRow>  $column
     */
    public function isColumn(SortableColumn $column): bool
    {
        return $this->column === $column;
    }

    /**
     * The `aria-sort` value `$column`'s header carries: this sort's direction on the sorted column, none on every other.
     *
     * @param  SortableColumn<TRow>  $column
     */
    public function ariaSort(SortableColumn $column): ?string
    {
        return $this->isColumn($column) ? $this->direction->ariaSort() : null;
    }

    /**
     * The direction a click on `$column`'s header would produce: the
     * flip of the current direction when it is already the sorted
     * column, descending otherwise.
     *
     * @param  SortableColumn<TRow>  $column
     */
    public function nextDirectionFor(SortableColumn $column): SortDirection
    {
        return $this->isColumn($column) ? $this->direction->flipped() : SortDirection::Desc;
    }
}
