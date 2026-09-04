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

    public function label(): string
    {
        return match ($this) {
            self::List => 'List',
            self::Table => 'Table',
            self::Grid => 'Grid',
        };
    }
}
