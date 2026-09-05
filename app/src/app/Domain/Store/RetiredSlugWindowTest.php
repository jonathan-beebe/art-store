<?php

declare(strict_types=1);

namespace App\Domain\Store;

use DateTimeImmutable;

it('opens the window thirty days back', function (): void {
    $opens = RetiredSlugWindow::opensAt(new DateTimeImmutable('2026-09-03 10:00:00'));

    expect($opens->format('Y-m-d H:i:s'))->toBe('2026-08-04 10:00:00');
});

it('forwards from an address retired inside the window', function (string $retiredAt): void {
    expect(RetiredSlugWindow::stillForwards(
        new DateTimeImmutable($retiredAt),
        new DateTimeImmutable('2026-09-03 10:00:00'),
    ))->toBeTrue();
})->with([
    'yesterday' => '2026-09-02 10:00:00',
    'the moment the window opens' => '2026-08-04 10:00:00',
]);

it('answers nothing for an address retired before the window', function (string $retiredAt): void {
    expect(RetiredSlugWindow::stillForwards(
        new DateTimeImmutable($retiredAt),
        new DateTimeImmutable('2026-09-03 10:00:00'),
    ))->toBeFalse();
})->with([
    'a second early' => '2026-08-04 09:59:59',
    'a year ago' => '2025-09-03 10:00:00',
]);
