<?php

declare(strict_types=1);

namespace App\Domain\Analytics;

use DateTimeImmutable;
use DateTimeZone;

/**
 * The calendar day a moment rolls up under, always read in UTC regardless of
 * the timezone the moment itself carries — the day a hit counts against
 * cannot depend on which clock minted the instant.
 */
final class PageViewDay
{
    private function __construct() {} // @codeCoverageIgnore

    public static function of(DateTimeImmutable $now): string
    {
        return $now->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d');
    }
}
