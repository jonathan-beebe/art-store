<?php

declare(strict_types=1);

namespace App\Seller;

use App\Domain\Analytics\AnalyticsRange;

/**
 * The dashboard's one control: the range the whole page is read over, as
 * the segmented links `x-seller.segmented` renders. The class that knows
 * the route builds the hrefs, so the view stays a renderer.
 */
final readonly class DashboardChrome
{
    private function __construct() {} // @codeCoverageIgnore

    /**
     * @return list<NavLink>
     */
    public static function rangeLinks(AnalyticsRange $range): array
    {
        return array_map(fn (int $days): NavLink => new NavLink(
            label: $days.' days',
            href: route('seller.dashboard', ['range' => $days]),
            active: $days === $range->days,
        ), AnalyticsRange::SIZES);
    }
}
