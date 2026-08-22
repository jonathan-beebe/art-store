<?php

namespace App\Domain\Reports;

use PHPUnit\Framework\TestCase;

final class DailyActivityTest extends TestCase
{
    public function test_it_labels_the_day_for_a_table_row(): void
    {
        $day = new DailyActivity('2026-08-09', 3, 1, 0);

        $this->assertSame('Aug 9', $day->label());
    }

    public function test_it_sums_the_three_event_kinds(): void
    {
        $day = new DailyActivity('2026-08-09', 3, 1, 2);

        $this->assertSame(6, $day->total());
    }
}
