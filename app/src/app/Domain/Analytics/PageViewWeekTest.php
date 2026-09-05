<?php

declare(strict_types=1);

namespace App\Domain\Analytics;

it('is the seven days ending today', function (string $today, string $firstDay): void {
    $week = PageViewWeek::endingOn($today);

    expect($week->firstDay)->toBe($firstDay)
        ->and($week->lastDay)->toBe($today);
})->with([
    'an ordinary day' => ['2026-08-24', '2026-08-18'],
    'reaching back over the end of a month' => ['2026-09-02', '2026-08-27'],
    'reaching back over the end of a year' => ['2027-01-03', '2026-12-28'],
]);
