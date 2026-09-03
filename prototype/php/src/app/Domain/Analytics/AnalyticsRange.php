<?php

declare(strict_types=1);

namespace App\Domain\Analytics;

use DateTimeImmutable;
use DateTimeZone;

/**
 * A window of whole UTC days the admin analytics pages compare against —
 * `of()` floors whatever moment it is handed to that day's midnight, so a
 * range always starts and ends on a day boundary regardless of the time of
 * day it was built at. {@see previous()} is the same number of days
 * immediately before this one, what every "this range vs the one before"
 * comparison on the page reads against.
 */
final readonly class AnalyticsRange
{
    /** The only window sizes the admin analytics pages offer. */
    public const array SIZES = [7, 30, 90];

    private function __construct(
        public int $days,
        public DateTimeImmutable $start,
        public DateTimeImmutable $end,
    ) {}

    /**
     * `$days` whole UTC days ending on the day `$endsOn` falls in —
     * `$start`/`$end` are that first/last day's midnight-to-midnight
     * bounds, both inclusive.
     */
    public static function of(int $days, DateTimeImmutable $endsOn): self
    {
        $endDay = self::floorToDay($endsOn);
        $startDay = $endDay->modify('-'.($days - 1).' days');

        return new self($days, $startDay, $endDay->modify('+1 day -1 second'));
    }

    /**
     * The `$days`-day window immediately before this one — this range
     * shifted back by its own length, so the two windows never overlap
     * and never leave a day uncovered between them.
     */
    public function previous(): self
    {
        $previousEnd = $this->start->modify('-1 second');
        $previousStart = $this->start->modify('-'.$this->days.' days');

        return new self($this->days, $previousStart, $previousEnd);
    }

    /**
     * "Aug 4 – Sep 2 vs Jul 5 – Aug 3" — this range's days against the
     * same number of days immediately before them.
     */
    public function caption(): string
    {
        $previous = $this->previous();

        return self::formatDay($this->start).' – '.self::formatDay($this->end)
            .' vs '.self::formatDay($previous->start).' – '.self::formatDay($previous->end);
    }

    /**
     * Every day in the range, oldest first — the array-order every daily
     * series (a bar strip's counts, a query's day-keyed totals) is read
     * against.
     *
     * @return list<string> Y-m-d
     */
    public function dayLabels(): array
    {
        $labels = [];
        $cursor = $this->start;

        for ($i = 0; $i < $this->days; $i++) {
            $labels[] = $cursor->format('Y-m-d');
            $cursor = $cursor->modify('+1 day');
        }

        return $labels;
    }

    /** "Sep 1" for a bar's tooltip — one entry of {@see dayLabels()} read back as a short caption. */
    public static function dayCaption(string $ymd): string
    {
        return self::formatDay(new DateTimeImmutable($ymd, new DateTimeZone('UTC')));
    }

    private static function floorToDay(DateTimeImmutable $moment): DateTimeImmutable
    {
        return new DateTimeImmutable($moment->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d').' 00:00:00', new DateTimeZone('UTC'));
    }

    private static function formatDay(DateTimeImmutable $day): string
    {
        return $day->format('M j');
    }
}
