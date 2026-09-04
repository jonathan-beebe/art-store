<?php

declare(strict_types=1);

namespace App\Domain\Seller;

/**
 * The customers table's seven sortable columns.
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

    /**
     * The sort the table opens on: what each buyer has spent, largest first.
     *
     * @return TableSort<CustomerRow>
     */
    public static function defaultSort(): TableSort
    {
        return TableSort::of(self::Spent, SortDirection::Desc);
    }

    /**
     * The value one row sorts by on this column.
     *
     * @param  CustomerRow  $row
     */
    public function keyOf(object $row): int|string
    {
        return match ($this) {
            self::Name => mb_strtolower($row->name),
            self::Orders => $row->orders,
            self::Spent => $row->spentCents,
            self::Favorites => $row->favorites,
            self::LastOrder => $row->lastOrderAt->getTimestamp(),
            self::Conversations => $row->conversations,
            self::Since => $row->firstOrderAt->getTimestamp(),
        };
    }
}
