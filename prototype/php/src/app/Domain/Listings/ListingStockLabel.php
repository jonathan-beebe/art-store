<?php

declare(strict_types=1);

namespace App\Domain\Listings;

/**
 * How `listings.quantity` reads wherever a person sees it: a plain count, or
 * "Made to order" for the null, uncapped reading a seller reaches through the
 * "Made to order" checkbox on create or Basics — the same craft word the
 * variant-level stock screen already uses for its own null quantity
 * (`resources/views/seller/listings/variants/index.blade.php`).
 */
final class ListingStockLabel
{
    private function __construct() {} // @codeCoverageIgnore

    public static function bare(?int $quantity): string
    {
        return $quantity === null ? 'Made to order' : (string) $quantity;
    }

    public static function withInStock(?int $quantity): string
    {
        return $quantity === null ? 'Made to order' : "{$quantity} in stock";
    }
}
