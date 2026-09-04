<?php

declare(strict_types=1);

namespace App\Domain\Seller;

/**
 * The seller listings table's eleven sortable columns.
 *
 * @implements SortableColumn<ListingTableRow>
 */
enum ListingSortColumn: string implements SortableColumn
{
    case Title = 'title';
    case Status = 'status';
    case Price = 'price';
    case Stock = 'stock';
    case Views = 'views';
    case Favorites = 'favorites';
    case CartAdds = 'cart_adds';
    case Sold = 'sold';
    case Revenue = 'revenue';
    case Conversion = 'conversion';
    case Updated = 'updated';

    public function label(): string
    {
        return match ($this) {
            self::Title => 'Listing',
            self::Status => 'Status',
            self::Price => 'Price',
            self::Stock => 'Stock',
            self::Views => 'Views (last 30 days)',
            self::Favorites => 'Favorites (last 30 days)',
            self::CartAdds => 'Cart adds (last 30 days)',
            self::Sold => 'Sold',
            self::Revenue => 'Revenue',
            self::Conversion => 'Conversion',
            self::Updated => 'Updated',
        };
    }

    /** Every column right-aligns its numbers except the listing itself and its status. */
    public function alignsRight(): bool
    {
        return $this !== self::Title && $this !== self::Status;
    }

    /**
     * The sort the table opens on: the listing buyers are looking at, most first.
     *
     * @return TableSort<ListingTableRow>
     */
    public static function defaultSort(): TableSort
    {
        return TableSort::of(self::Views, SortDirection::Desc);
    }

    /**
     * The value one row sorts by on this column. A listing with no views
     * yet reads as the lowest conversion, keeping it in the order;
     * made-to-order stock (a null quantity) reads as unlimited, so it
     * sorts above every counted number.
     *
     * @param  ListingTableRow  $row
     */
    public function keyOf(object $row): int|float|string
    {
        return match ($this) {
            self::Title => mb_strtolower($row->title),
            self::Status => mb_strtolower($row->statusLabel),
            self::Price => $row->priceCents,
            self::Stock => $row->quantity ?? PHP_INT_MAX,
            self::Views => $row->views,
            self::Favorites => $row->favorites,
            self::CartAdds => $row->cartAdds,
            self::Sold => $row->sold,
            self::Revenue => $row->revenueCents,
            self::Conversion => $row->conversion() ?? -1.0,
            self::Updated => $row->updatedAt->getTimestamp(),
        };
    }
}
