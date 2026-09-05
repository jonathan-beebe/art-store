<?php

declare(strict_types=1);

namespace App\Domain\Analytics;

use DateTimeImmutable;

/**
 * "This week" where traffic is concerned: the seven days ending today, not
 * Monday-to-Sunday. A calendar week reads as almost nothing every Monday,
 * and the number on the dashboard exists to be compared with the day before
 * it. The payout period is a calendar week and answers a different
 * question — see `App\Domain\Escrow\PayoutPeriod`.
 */
final readonly class PageViewWeek
{
    private const int DAYS = 7;

    private function __construct(public string $firstDay, public string $lastDay) {}

    /** @param string $today  Y-m-d, the day the window ends on */
    public static function endingOn(string $today): self
    {
        $last = new DateTimeImmutable($today.'T00:00:00+00:00');
        $first = $last->modify('-'.(self::DAYS - 1).' days');

        return new self($first->format('Y-m-d'), $today);
    }
}
