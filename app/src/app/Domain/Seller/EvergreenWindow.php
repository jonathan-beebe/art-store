<?php

declare(strict_types=1);

namespace App\Domain\Seller;

/**
 * Listings and customers are evergreen resources with no range control
 * of their own. Every ranged figure on either page — the listings
 * table's Views/Favorites/Cart adds columns, the customers table's "New
 * this period" segment — reads this many days.
 */
final class EvergreenWindow
{
    private function __construct() {} // @codeCoverageIgnore

    public const int DAYS = 30;
}
