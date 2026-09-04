<?php

declare(strict_types=1);

namespace App\Domain\Seller;

/**
 * The three ways the seller listings tool renders one seller's inventory.
 */
enum ListingView: string
{
    case List = 'list';
    case Table = 'table';
    case Grid = 'grid';

    public static function default(): self
    {
        return self::List;
    }

    /** Only Table and Grid carry a sort control; List keeps its own order. */
    public function showsSort(): bool
    {
        return $this !== self::List;
    }

    /**
     * The views a listing's detail may be opened from via `?from=` —
     * every view but List, which shows the detail beside the list
     * instead of linking to it.
     *
     * @return list<self>
     */
    public static function openable(): array
    {
        return [self::Table, self::Grid];
    }

    public function label(): string
    {
        return match ($this) {
            self::List => 'List',
            self::Table => 'Table',
            self::Grid => 'Grid',
        };
    }

    /** The view switch's icon, one `<path d="">` per view. */
    public function iconPath(): string
    {
        return match ($this) {
            self::List => 'M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5',
            self::Table => 'M3.75 5.25h16.5v13.5H3.75zM3.75 9.75h16.5M3.75 14.25h16.5M9.75 5.25v13.5M15.75 5.25v13.5',
            self::Grid => 'M3.75 6A2.25 2.25 0 0 1 6 3.75h2.25A2.25 2.25 0 0 1 10.5 6v2.25a2.25 2.25 0 0 1-2.25 2.25H6a2.25 2.25 0 0 1-2.25-2.25V6ZM3.75 15.75A2.25 2.25 0 0 1 6 13.5h2.25a2.25 2.25 0 0 1 2.25 2.25V18a2.25 2.25 0 0 1-2.25 2.25H6A2.25 2.25 0 0 1 3.75 18v-2.25ZM13.5 6a2.25 2.25 0 0 1 2.25-2.25H18A2.25 2.25 0 0 1 20.25 6v2.25A2.25 2.25 0 0 1 18 10.5h-2.25a2.25 2.25 0 0 1-2.25-2.25V6ZM13.5 15.75a2.25 2.25 0 0 1 2.25-2.25H18a2.25 2.25 0 0 1 2.25 2.25V18A2.25 2.25 0 0 1 18 20.25h-2.25A2.25 2.25 0 0 1 13.5 18v-2.25Z',
        };
    }
}
