<?php

declare(strict_types=1);

namespace App\Domain\Auth;

use DateTimeImmutable;

it('derives status from expiry, consumption, and now', function (
    string $expiresAt,
    ?string $consumedAt,
    string $now,
    MagicLinkStatus $expected,
): void {
    $status = MagicLinkStatus::of(
        new DateTimeImmutable($expiresAt),
        $consumedAt === null ? null : new DateTimeImmutable($consumedAt),
        new DateTimeImmutable($now),
    );

    expect($status)->toBe($expected);
})->with([
    'a fresh unconsumed link is usable' => ['2026-08-22 12:15:00', null, '2026-08-22 12:00:00', MagicLinkStatus::Usable],
    'a link is expired once now reaches the expiry' => ['2026-08-22 12:15:00', null, '2026-08-22 12:15:00', MagicLinkStatus::Expired],
    'a link is expired after the expiry' => ['2026-08-22 12:15:00', null, '2026-08-22 12:15:01', MagicLinkStatus::Expired],
    'a consumed link is consumed' => ['2026-08-22 12:15:00', '2026-08-22 12:05:00', '2026-08-22 12:06:00', MagicLinkStatus::Consumed],
    'consumption outranks expiry' => ['2026-08-22 12:15:00', '2026-08-22 12:05:00', '2026-08-22 13:00:00', MagicLinkStatus::Consumed],
]);
