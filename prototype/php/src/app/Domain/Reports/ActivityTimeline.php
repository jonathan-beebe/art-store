<?php

declare(strict_types=1);

namespace App\Domain\Reports;

use App\Analytics\AnalyticsEventName;
use DateTimeImmutable;
use InvalidArgumentException;

final class ActivityTimeline
{
    private function __construct() {} // @codeCoverageIgnore

    /**
     * A gapless run of days ending on the day of $endsOn, oldest first.
     *
     * @param  array<string, array<string, int>>  $countsByDate  day (Y-m-d) => event name value => count
     * @return list<DailyActivity>
     */
    public static function lastDays(array $countsByDate, DateTimeImmutable $endsOn, int $days): array
    {
        $first = self::firstDay($endsOn, $days);

        return array_map(
            fn (int $offset): DailyActivity => self::day($countsByDate, $first->modify("+{$offset} days")),
            range(0, $days - 1),
        );
    }

    /**
     * The midnight the window opens at — the earliest moment a timeline of
     * $days ending on $endsOn has a row for.
     */
    public static function firstDay(DateTimeImmutable $endsOn, int $days): DateTimeImmutable
    {
        if ($days < 1) {
            throw new InvalidArgumentException("A timeline covers at least one day, got {$days}.");
        }

        return $endsOn->setTime(0, 0)->modify('-'.($days - 1).' days');
    }

    /**
     * @param  array<string, array<string, int>>  $countsByDate
     */
    private static function day(array $countsByDate, DateTimeImmutable $on): DailyActivity
    {
        $counts = $countsByDate[$on->format('Y-m-d')] ?? [];

        return DailyActivity::on(
            $on,
            $counts[AnalyticsEventName::ListingView->value] ?? 0,
            $counts[AnalyticsEventName::ListingFavorite->value] ?? 0,
            $counts[AnalyticsEventName::ListingCartAdd->value] ?? 0,
        );
    }
}
