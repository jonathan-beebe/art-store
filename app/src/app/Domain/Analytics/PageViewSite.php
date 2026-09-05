<?php

declare(strict_types=1);

namespace App\Domain\Analytics;

enum PageViewSite: string
{
    case Shop = 'shop';
    case Seller = 'seller';
    case Admin = 'admin';

    /**
     * Which side of the marketplace a route pattern belongs to. The
     * storefront claims no prefix of its own, so it is what a pattern is
     * when no portal claims it — which is also why a future storefront page
     * under `/sellers-guide` stays on the storefront rather than reading as
     * the seller portal's.
     */
    public static function fromRoutePattern(string $pattern): self
    {
        return match (true) {
            $pattern === '/seller' || str_starts_with($pattern, '/seller/') => self::Seller,
            $pattern === '/admin' || str_starts_with($pattern, '/admin/') => self::Admin,
            default => self::Shop,
        };
    }
}
