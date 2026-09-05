<?php

declare(strict_types=1);

namespace App\Domain\Reports;

use DateTimeImmutable;

it('labels the day for a table row', function (): void {
    $day = DailyActivity::on(new DateTimeImmutable('2026-08-09'), 3, 1, 0);

    expect($day->label())->toBe('Aug 9');
});

it('sums the three event kinds', function (): void {
    $day = DailyActivity::on(new DateTimeImmutable('2026-08-09'), 3, 1, 2);

    expect($day->total())->toBe(6);
});
