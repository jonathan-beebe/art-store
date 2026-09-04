<?php

declare(strict_types=1);

namespace App\Domain\Seller;

/**
 * The customers table's seven sortable columns. The table sorts in SQL
 * ({@see \App\Seller\SellerCustomers}), so this carries no `keyOf()` —
 * only the label and alignment {@see \App\Seller\ColumnHeaders} renders
 * a header from.
 *
 * @implements SortableColumn<CustomerRow>
 */
enum CustomerSortColumn: string implements SortableColumn
{
    case Name = 'name';
    case Orders = 'orders';
    case Spent = 'spent';
    case Favorites = 'favorites';
    case LastOrder = 'last_order';
    case Conversations = 'conversations';
    case Since = 'since';

    public function label(): string
    {
        return match ($this) {
            self::Name => 'Customer',
            self::Orders => 'Orders',
            self::Spent => 'Spent',
            self::Favorites => 'Favorites',
            self::LastOrder => 'Last order',
            self::Conversations => 'Conversations',
            self::Since => 'Since',
        };
    }

    /** The counted columns right-align; the name and the two dates read as text. */
    public function alignsRight(): bool
    {
        return match ($this) {
            self::Orders, self::Spent, self::Favorites, self::Conversations => true,
            self::Name, self::LastOrder, self::Since => false,
        };
    }

    /** What each buyer has spent, largest first. */
    public static function default(): self
    {
        return self::Spent;
    }

    /**
     * The sort the table opens on.
     *
     * @return TableSort<CustomerRow>
     */
    public static function defaultSort(): TableSort
    {
        return TableSort::of(self::default(), SortDirection::Desc);
    }
}
