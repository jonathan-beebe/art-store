<?php

declare(strict_types=1);

namespace App\Analytics\Admin;

use App\Domain\Analytics\AnalyticsEventName;
use App\Domain\Analytics\AnalyticsRange;

/**
 * The range and event-name segmented controls an entity page (a listing's,
 * a store's, or an actor's) builds the same way: one link per
 * {@see AnalyticsRange::SIZES} or per event name, every other query
 * parameter carried through unchanged. `$routeName` and `$paramKey` name
 * the page's own route and the id its URI binds under, so one pair of
 * builders serves every entity page rather than each controller writing
 * its own copy.
 */
final class EntityPageLinks
{
    private function __construct() {} // @codeCoverageIgnore

    private const array RANGE_LABELS = [7 => '7d', 30 => '30d', 90 => '90d'];

    /**
     * @param  array<string, string>  $roundTripped
     * @return list<array{label: string, href: string, active: bool}>
     */
    public static function range(string $routeName, string $paramKey, string $id, array $roundTripped, int $current): array
    {
        $without = collect($roundTripped)->except('range')->all();

        return array_map(
            fn (int $days): array => [
                'label' => self::RANGE_LABELS[$days],
                'href' => route($routeName, [$paramKey => $id, ...$without, 'range' => $days]),
                'active' => $days === $current,
            ],
            AnalyticsRange::SIZES,
        );
    }

    /**
     * "All" plus one link per name in `$eventNames`.
     *
     * @param  array<string, string>  $roundTripped
     * @param  list<AnalyticsEventName>  $eventNames
     * @return list<array{label: string, href: string, active: bool}>
     */
    public static function event(string $routeName, string $paramKey, string $id, array $roundTripped, ?AnalyticsEventName $current, array $eventNames): array
    {
        $without = collect($roundTripped)->except('event')->all();

        $links = [[
            'label' => 'All',
            'href' => route($routeName, [$paramKey => $id, ...$without]),
            'active' => $current === null,
        ]];

        foreach ($eventNames as $name) {
            $links[] = [
                'label' => $name->pluralLabel(),
                'href' => route($routeName, [$paramKey => $id, ...$without, 'event' => $name->value]),
                'active' => $current === $name,
            ];
        }

        return $links;
    }
}
