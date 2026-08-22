<?php

namespace App\Domain\Reports;

use App\Domain\Listings\ListingEventType;
use DateTimeImmutable;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class ActivityTimelineTest extends TestCase
{
    public function test_it_returns_one_row_per_day_oldest_first(): void
    {
        $days = ActivityTimeline::lastDays([], new DateTimeImmutable('2026-08-22 17:30:00'), 3);

        $this->assertCount(3, $days);
        $this->assertSame(['2026-08-20', '2026-08-21', '2026-08-22'], array_map(fn (DailyActivity $day): string => $day->date, $days));
    }

    public function test_it_reads_counts_by_date_and_event_type(): void
    {
        $counts = [
            '2026-08-21' => [
                ListingEventType::View->value => 4,
                ListingEventType::Favorite->value => 1,
                ListingEventType::CartAdd->value => 2,
            ],
        ];

        $days = ActivityTimeline::lastDays($counts, new DateTimeImmutable('2026-08-22 09:00:00'), 2);

        $this->assertSame(4, $days[0]->views);
        $this->assertSame(1, $days[0]->favorites);
        $this->assertSame(2, $days[0]->cartAdds);
    }

    public function test_it_fills_days_with_no_events_with_zeroes(): void
    {
        $counts = ['2026-08-22' => [ListingEventType::View->value => 7]];

        $days = ActivityTimeline::lastDays($counts, new DateTimeImmutable('2026-08-22 09:00:00'), 2);

        $this->assertSame(0, $days[0]->views);
        $this->assertSame(0, $days[0]->favorites);
        $this->assertSame(7, $days[1]->views);
    }

    public function test_it_ignores_counts_outside_the_window(): void
    {
        $counts = ['2026-07-01' => [ListingEventType::View->value => 99]];

        $days = ActivityTimeline::lastDays($counts, new DateTimeImmutable('2026-08-22 09:00:00'), 2);

        $this->assertSame(0, array_sum(array_map(fn (DailyActivity $day): int => $day->total(), $days)));
    }

    public function test_it_ignores_event_types_the_report_does_not_show(): void
    {
        $counts = ['2026-08-22' => [ListingEventType::Unfavorite->value => 5]];

        $days = ActivityTimeline::lastDays($counts, new DateTimeImmutable('2026-08-22 09:00:00'), 1);

        $this->assertSame(0, $days[0]->total());
    }

    public function test_it_rejects_a_window_shorter_than_a_day(): void
    {
        $this->expectException(InvalidArgumentException::class);

        ActivityTimeline::lastDays([], new DateTimeImmutable('2026-08-22 09:00:00'), 0);
    }
}
