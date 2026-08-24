<?php

declare(strict_types=1);

namespace App\Domain\Listings;

use DateTimeImmutable;
use DateTimeZone;

it('collapses a view but records every deliberate interaction', function (ListingEventType $type, bool $collapses): void {
    expect(ListingViewCollapse::collapsesHourly($type))->toBe($collapses);
})->with([
    'view' => [ListingEventType::View, true],
    'favorite' => [ListingEventType::Favorite, false],
    'unfavorite' => [ListingEventType::Unfavorite, false],
    'cart_add' => [ListingEventType::CartAdd, false],
]);

it('maps the start of the hour to itself', function (): void {
    $start = new DateTimeImmutable('2026-08-22T14:00:00+00:00');

    expect(ListingViewCollapse::windowStart($start)->format('Y-m-d\TH:i:sP'))->toBe('2026-08-22T14:00:00+00:00');
});

it('maps the last moment of the hour back to its start', function (): void {
    $end = new DateTimeImmutable('2026-08-22T14:59:59.999999+00:00');

    expect(ListingViewCollapse::windowStart($end)->format('Y-m-d\TH:i:sP'))->toBe('2026-08-22T14:00:00+00:00');
});

it('maps a moment mid-hour to that hour\'s start', function (): void {
    $mid = new DateTimeImmutable('2026-08-22T14:32:07+00:00');

    expect(ListingViewCollapse::windowStart($mid)->format('Y-m-d\TH:i:s'))->toBe('2026-08-22T14:00:00');
});

it('rolls the date forward across a midnight hour boundary', function (): void {
    $justAfterMidnight = new DateTimeImmutable('2026-08-23T00:00:00+00:00');

    expect(ListingViewCollapse::windowStart($justAfterMidnight)->format('Y-m-d\TH:i:s'))->toBe('2026-08-23T00:00:00');
});

it('reads the hour in UTC regardless of the timezone the moment carries', function (): void {
    $mid = new DateTimeImmutable('2026-08-22T16:32:00', new DateTimeZone('+02:00'));

    expect(ListingViewCollapse::windowStart($mid)->format('Y-m-d\TH:i:sP'))->toBe('2026-08-22T14:00:00+00:00');
});
