<?php

declare(strict_types=1);

namespace App\Domain\Seller;

use DateTimeImmutable;

$row = function (string $id, int $orders, string $firstOrderAt): CustomerRow {
    return new CustomerRow(
        customerId: $id,
        name: 'Luna Lovegood',
        email: 'luna@example.test',
        orders: $orders,
        spentCents: 1000,
        favorites: 0,
        conversations: 0,
        firstOrderAt: new DateTimeImmutable($firstOrderAt),
        lastOrderAt: new DateTimeImmutable('2026-09-01 09:00:00'),
    );
};

$rangeStart = new DateTimeImmutable('2026-08-01 00:00:00');

it('opens on every buyer', function (): void {
    expect(CustomerSegment::default())->toBe(CustomerSegment::All);
});

it('names each segment', function (CustomerSegment $segment, string $expected): void {
    expect($segment->label())->toBe($expected);
})->with([
    [CustomerSegment::All, 'All'],
    [CustomerSegment::Repeat, 'Repeat buyers'],
    [CustomerSegment::New, 'New this period'],
]);

it('keeps every buyer under All', function () use ($row, $rangeStart): void {
    $rows = [$row('a', 1, '2026-01-01 09:00:00'), $row('b', 3, '2026-08-14 09:00:00')];

    expect(CustomerSegment::All->apply($rows, $rangeStart))->toBe($rows);
});

it('keeps only buyers with two or more orders under Repeat buyers', function () use ($row, $rangeStart): void {
    $rows = [$row('a', 1, '2026-01-01 09:00:00'), $row('b', 2, '2026-01-01 09:00:00')];

    $kept = CustomerSegment::Repeat->apply($rows, $rangeStart);

    expect(array_map(fn (CustomerRow $kept): string => $kept->customerId, $kept))->toBe(['b']);
});

it('keeps only buyers whose first order falls inside the range under New this period', function () use ($row, $rangeStart): void {
    $rows = [$row('a', 1, '2026-07-31 23:59:59'), $row('b', 1, '2026-08-14 09:00:00')];

    $kept = CustomerSegment::New->apply($rows, $rangeStart);

    expect(array_map(fn (CustomerRow $kept): string => $kept->customerId, $kept))->toBe(['b']);
});

it('hands back a list with the keys renumbered', function () use ($row, $rangeStart): void {
    $rows = [$row('a', 1, '2026-01-01 09:00:00'), $row('b', 2, '2026-01-01 09:00:00')];

    expect(array_keys(CustomerSegment::Repeat->apply($rows, $rangeStart)))->toBe([0]);
});
