<?php

declare(strict_types=1);

namespace App\Domain\Listings;

use DateTimeImmutable;
use DateTimeZone;

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

it('keys a dedupe on the listing, the customer, and the hour', function (): void {
    $at = new DateTimeImmutable('2026-08-22T14:32:00+00:00');

    expect(ListingViewCollapse::dedupeKey('lst_ABC', 'cus_XYZ', $at))
        ->toBe('listing:lst_ABC:customer:cus_XYZ:hour:2026-08-22T14');
});

it('keys an anonymous view apart from an attributed one in the same hour', function (): void {
    $at = new DateTimeImmutable('2026-08-22T14:32:00+00:00');
    $anonymousKey = ListingViewCollapse::dedupeKey('lst_ABC', null, $at);

    expect($anonymousKey)->toBe('listing:lst_ABC:customer:anonymous:hour:2026-08-22T14')
        ->and($anonymousKey)->not->toBe(ListingViewCollapse::dedupeKey('lst_ABC', 'cus_XYZ', $at));
});

it('keys two views inside the same hour alike, and one in the next hour apart', function (): void {
    $firstInHour = new DateTimeImmutable('2026-08-22T14:00:00+00:00');
    $lastInHour = new DateTimeImmutable('2026-08-22T14:59:59+00:00');
    $nextHour = new DateTimeImmutable('2026-08-22T15:00:00+00:00');
    $key = ListingViewCollapse::dedupeKey('lst_ABC', 'cus_XYZ', $firstInHour);

    expect($key)->toBe(ListingViewCollapse::dedupeKey('lst_ABC', 'cus_XYZ', $lastInHour))
        ->and($key)->not->toBe(ListingViewCollapse::dedupeKey('lst_ABC', 'cus_XYZ', $nextHour));
});
