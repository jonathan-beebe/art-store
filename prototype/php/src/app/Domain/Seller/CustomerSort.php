<?php

declare(strict_types=1);

namespace App\Domain\Seller;

/**
 * The customers table's current sort: one column, one direction. The
 * table opens on what each buyer has spent, the figure a seller reads the
 * list for.
 */
final readonly class CustomerSort
{
    private function __construct(
        public CustomerSortColumn $column,
        public SortDirection $direction,
    ) {}

    public static function of(CustomerSortColumn $column, SortDirection $direction): self
    {
        return new self($column, $direction);
    }

    public static function default(): self
    {
        return new self(CustomerSortColumn::Spent, SortDirection::Desc);
    }

    public function isColumn(CustomerSortColumn $column): bool
    {
        return $this->column === $column;
    }

    /** The `aria-sort` value `$column`'s header carries: this sort's direction on the sorted column, none on every other. */
    public function ariaSort(CustomerSortColumn $column): ?string
    {
        return $this->isColumn($column) ? $this->direction->ariaSort() : null;
    }

    /**
     * The direction a click on `$column`'s header would produce: the flip
     * of the current direction when it is already the sorted column,
     * descending otherwise.
     */
    public function nextDirectionFor(CustomerSortColumn $column): SortDirection
    {
        return $this->isColumn($column) ? $this->direction->flipped() : SortDirection::Desc;
    }
}
