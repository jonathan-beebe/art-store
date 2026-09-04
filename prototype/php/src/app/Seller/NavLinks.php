<?php

declare(strict_types=1);

namespace App\Seller;

/**
 * One `NavLink` per case of a control that switches a page's whole view by
 * one query parameter — the listings view switch, the customers segment
 * control, the dashboard range control. The class that knows the route
 * builds every href, so the view stays a renderer.
 */
final class NavLinks
{
    private function __construct() {} // @codeCoverageIgnore

    /**
     * @template TCase
     *
     * @param  array<string, string>  $without  the round-tripped filters with `$param` itself dropped
     * @param  list<TCase>  $cases
     * @param  callable(TCase): string  $label
     * @param  callable(TCase): string  $value
     * @param  callable(TCase): bool  $active
     * @param  ?callable(TCase): string  $iconPath  one `<path d="">` per case, for a control that draws one
     * @return list<NavLink>
     */
    public static function for(
        string $routeName,
        array $without,
        string $param,
        array $cases,
        callable $label,
        callable $value,
        callable $active,
        ?callable $iconPath = null,
    ): array {
        return array_map(fn ($case): NavLink => new NavLink(
            label: $label($case),
            href: route($routeName, [...$without, $param => $value($case)]),
            active: $active($case),
            iconPath: $iconPath === null ? null : $iconPath($case),
        ), $cases);
    }
}
