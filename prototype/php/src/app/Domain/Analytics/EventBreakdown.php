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
