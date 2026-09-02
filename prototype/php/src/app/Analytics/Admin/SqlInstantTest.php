<?php

declare(strict_types=1);

namespace App\Analytics\Admin;

use DateTimeImmutable;

it('formats a moment the way analytics_events.occurred_at compares against it', function (): void {
    $moment = new DateTimeImmutable('2026-08-19T14:05:30+00:00');

    expect(SqlInstant::format($moment))->toBe('2026-08-19 14:05:30');
});
