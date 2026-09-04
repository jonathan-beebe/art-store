<?php

declare(strict_types=1);

namespace App\Seller;

use App\Domain\Seller\ActivityKind;

/**
 * The segmented control above an activity feed: All, then one button per
 * kind, each linking to the page it sits on with `?kind=` set. The page
 * fetches every source either way and the domain filters
 * ({@see \App\Domain\Seller\ActivityFeed::filter()}), so the buttons only
 * ever change the URL.
 */
final class FeedFilters
{
    private function __construct() {} // @codeCoverageIgnore

    /**
     * @param  array<string, string>  $routeParams  what identifies the page the feed sits on
     * @return list<NavLink>
     */
    public static function for(string $routeName, array $routeParams, ?ActivityKind $current): array
    {
        $links = [new NavLink(
            label: 'All',
            href: route($routeName, $routeParams),
            active: ! $current instanceof ActivityKind,
        )];

        foreach (ActivityKind::cases() as $kind) {
            $links[] = new NavLink(
                label: $kind->label(),
                href: route($routeName, [...$routeParams, 'kind' => $kind->value]),
                active: $kind === $current,
            );
        }

        return $links;
    }
}
