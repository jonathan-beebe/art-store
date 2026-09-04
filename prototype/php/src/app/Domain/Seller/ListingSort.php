<?php

declare(strict_types=1);

namespace App\Domain\Seller;

/**
 * A table or grid's current sort: one column, one direction. Table and
 * grid share the same default, since neither renders without the other's
 * counts.
 */
final readonly class ListingSort
{
    private function __construct(
        public ListingSortColumn $column,
        public SortDirection $direction,
    ) {}

    public static function of(ListingSortColumn $column, SortDirection $direction): self
    {
        return new self($column, $direction);
    }

    public static function default(): self
    {
        return new self(ListingSortColumn::Views, SortDirection::Desc);
    }

    public function isColumn(ListingSortColumn $column): bool
    {
        return $this->column === $column;
    }

    /** The `aria-sort` value `$column`'s header carries: this sort's direction on the sorted column, none on every other. */
    public function ariaSort(ListingSortColumn $column): ?string
    {
        return $this->isColumn($column) ? $this->direction->ariaSort() : null;
    }

    /**
     * The direction a click on `$column`'s header would produce: the
     * flip of the current direction when it is already the sorted
     * column, descending otherwise.
     */
    public function nextDirectionFor(ListingSortColumn $column): SortDirection
    {
        return $this->isColumn($column) ? $this->direction->flipped() : SortDirection::Desc;
    }
}
