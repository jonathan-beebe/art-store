<?php

declare(strict_types=1);

namespace App\Logging\Admin;

it('slices the time-of-day out of a fixed-shape ISO instant', function (): void {
    expect(LogTimestamp::timeOfDay('2026-08-24T12:04:18.412Z'))->toBe('12:04:18.412');
});

it('renders a shorter-than-expected value unchanged', function (): void {
    expect(LogTimestamp::timeOfDay('bad-ts'))->toBe('bad-ts')
        ->and(LogTimestamp::timeOfDay(''))->toBe('');
});
