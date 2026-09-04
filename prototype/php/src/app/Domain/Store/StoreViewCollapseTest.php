<?php

declare(strict_types=1);

namespace App\Domain\Store;

use DateTimeImmutable;
use DateTimeZone;

it('opens the window at the top of the UTC hour', function (): void {
    $window = StoreViewCollapse::windowStart(new DateTimeImmutable('2026-09-03 14:37:22', new DateTimeZone('UTC')));

    expect($window->format('Y-m-d H:i:s'))->toBe('2026-09-03 14:00:00');
});

it('reads the window in UTC whatever zone the moment carries', function (): void {
    $window = StoreViewCollapse::windowStart(new DateTimeImmutable('2026-09-03 01:15:00', new DateTimeZone('+03:00')));

    expect($window->format('Y-m-d H:i:s'))->toBe('2026-09-02 22:00:00');
});

it('collides on the same store, customer, and hour', function (): void {
    $first = StoreViewCollapse::dedupeKey('sto_1', 'cus_1', new DateTimeImmutable('2026-09-03 14:01:00', new DateTimeZone('UTC')));
    $second = StoreViewCollapse::dedupeKey('sto_1', 'cus_1', new DateTimeImmutable('2026-09-03 14:59:00', new DateTimeZone('UTC')));

    expect($first)->toBe($second)
        ->and($first)->toBe('store:sto_1:customer:cus_1:hour:2026-09-03T14');
});

it('keeps a different store, customer, or hour apart', function (string $storeId, ?string $customerId, string $at): void {
    $key = StoreViewCollapse::dedupeKey($storeId, $customerId, new DateTimeImmutable($at, new DateTimeZone('UTC')));

    expect($key)->not->toBe('store:sto_1:customer:cus_1:hour:2026-09-03T14');
})->with([
    'another store' => ['sto_2', 'cus_1', '2026-09-03 14:01:00'],
    'another customer' => ['sto_1', 'cus_2', '2026-09-03 14:01:00'],
    'the next hour' => ['sto_1', 'cus_1', '2026-09-03 15:01:00'],
]);

it('folds every anonymous visitor into one key', function (): void {
    $key = StoreViewCollapse::dedupeKey('sto_1', null, new DateTimeImmutable('2026-09-03 14:01:00', new DateTimeZone('UTC')));

    expect($key)->toBe('store:sto_1:customer:anonymous:hour:2026-09-03T14');
});
