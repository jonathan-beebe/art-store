<?php

declare(strict_types=1);

namespace App\Domain\Analytics;

/**
 * How the event page slices one event name's range: by the listing it
 * happened to, by the actor who caused it, or — for the `page.view`
 * roll-up, which carries neither — by the route pattern it hit.
 * {@see allowedFor()} and {@see defaultFor()} both key on the event name
 * string {@see \App\Analytics\Admin\EventDetail::forRange()} takes, since
 * `page.view` is not an {@see AnalyticsEventName} case.
 */
enum EventBreakdown: string
{
    case Listing = 'listing';
    case Actor = 'actor';
    case Pattern = 'pattern';

    /** The event name the `page.view` roll-up is recorded under. */
    public const string PAGE_VIEW_EVENT_NAME = 'page.view';

    /** The `page.view` roll-up's label everywhere the drill-in shows it —
     * it carries no {@see AnalyticsEventName} case of its own to hold one. */
    public const string PAGE_VIEW_LABEL = 'Page views';

    /** The `page.view` roll-up's icon path — a 24x24 outline, the same
     * shape every {@see AnalyticsEventName} case's {@see AnalyticsEventName::iconPath()} draws. */
    public const string PAGE_VIEW_ICON_PATH = 'M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9z';

    /**
     * `page.view` carries no subject or actor of its own, so a pattern is
     * the only breakdown it offers; every other event name offers the
     * other two.
     *
     * @return list<self>
     */
    public static function allowedFor(string $eventName): array
    {
        return $eventName === self::PAGE_VIEW_EVENT_NAME
            ? [self::Pattern]
            : [self::Listing, self::Actor];
    }

    public static function defaultFor(string $eventName): self
    {
        return $eventName === self::PAGE_VIEW_EVENT_NAME ? self::Pattern : self::Listing;
    }

    /** The event page's breakdown heading. */
    public function heading(): string
    {
        return match ($this) {
            self::Listing => 'By listing',
            self::Actor => 'By actor',
            self::Pattern => 'By pattern',
        };
    }

    /** The breakdown table's first column header. */
    public function columnLabel(): string
    {
        return match ($this) {
            self::Listing => 'Listing',
            self::Actor => 'Actor',
            self::Pattern => 'Pattern',
        };
    }
}
