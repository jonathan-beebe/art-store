<?php

declare(strict_types=1);

namespace App\Domain\Escrow;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class PayoutPeriodTest extends TestCase
{
    public function test_mid_week_pays_out_the_week_that_just_ended(): void
    {
        $period = PayoutPeriod::endingBefore(new DateTimeImmutable('2026-08-22 09:30:00'));

        $this->assertSame('2026-08-10 00:00:00', $period->start->format('Y-m-d H:i:s'));
        $this->assertSame('2026-08-16 23:59:59', $period->end->format('Y-m-d H:i:s'));
    }

    public function test_the_first_moment_of_a_monday_pays_out_the_week_before_it(): void
    {
        $period = PayoutPeriod::endingBefore(new DateTimeImmutable('2026-08-17 00:00:00'));

        $this->assertSame('2026-08-10 00:00:00', $period->start->format('Y-m-d H:i:s'));
        $this->assertSame('2026-08-16 23:59:59', $period->end->format('Y-m-d H:i:s'));
    }

    public function test_the_last_moment_of_a_sunday_still_pays_out_the_week_before_it(): void
    {
        $period = PayoutPeriod::endingBefore(new DateTimeImmutable('2026-08-16 23:59:59'));

        $this->assertSame('2026-08-03 00:00:00', $period->start->format('Y-m-d H:i:s'));
        $this->assertSame('2026-08-09 23:59:59', $period->end->format('Y-m-d H:i:s'));
    }

    public function test_a_period_covers_seven_days(): void
    {
        $period = PayoutPeriod::endingBefore(new DateTimeImmutable('2026-08-22 09:30:00'));

        $this->assertTrue($period->contains(new DateTimeImmutable('2026-08-10 00:00:00')));
        $this->assertTrue($period->contains(new DateTimeImmutable('2026-08-16 23:59:59')));
        $this->assertFalse($period->contains(new DateTimeImmutable('2026-08-09 23:59:59')));
        $this->assertFalse($period->contains(new DateTimeImmutable('2026-08-17 00:00:00')));
    }

    public function test_it_labels_itself_by_its_first_and_last_day(): void
    {
        $period = PayoutPeriod::endingBefore(new DateTimeImmutable('2026-08-22 09:30:00'));

        $this->assertSame('2026-08-10 to 2026-08-16', $period->label());
    }
}
