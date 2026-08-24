<?php

declare(strict_types=1);

namespace App\Domain\Analytics;

use DateTimeImmutable;
use DateTimeZone;

it('reads the UTC day', function (): void {
    $moment = new DateTimeImmutable('2026-08-22T23:59:59.999999+00:00');

    expect(PageViewDay::of($moment))->toBe('2026-08-22');
});

it('does not shift across a UTC day boundary', function (): void {
    $moment = new DateTimeImmutable('2026-08-23T00:00:00+00:00');

    expect(PageViewDay::of($moment))->toBe('2026-08-23');
});

it('reads a moment in another timezone as its UTC day, not its local one', function (): void {
    $moment = new DateTimeImmutable('2026-08-23T01:30:00', new DateTimeZone('-02:00'));

    expect(PageViewDay::of($moment))->toBe('2026-08-23');
});
