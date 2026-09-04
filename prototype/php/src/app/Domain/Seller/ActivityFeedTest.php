<?php

declare(strict_types=1);

namespace App\Domain\Seller;

use DateTimeImmutable;

/**
 * A feed event at a given instant, with no field beyond the instant and the
 * kind mattering to the merge and filter tests in this file.
 */
function feedEventAt(DateTimeImmutable $at, ActivityKind $kind = ActivityKind::Order): FeedEvent
{
    return new FeedEvent(
        occurredAt: $at,
        kind: $kind,
        icon: FeedIcon::Bag,
        actor: 'Harry Potter',
        text: 'did something',
    );
}

it('merges every source\'s rows into one feed, newest first', function (): void {
    $early = feedEventAt(new DateTimeImmutable('2026-08-20 09:00:00'));
    $late = feedEventAt(new DateTimeImmutable('2026-08-21 09:00:00'));
    $middle = feedEventAt(new DateTimeImmutable('2026-08-20 12:00:00'));

    $feed = ActivityFeed::merge([$early], [$late, $middle]);

    expect($feed->events)->toBe([$late, $middle, $early]);
});

it('keeps two rows at the same instant in the order their sources were passed', function (): void {
    $at = new DateTimeImmutable('2026-08-20 09:00:00');
    $first = feedEventAt($at);
    $second = feedEventAt($at);

    expect(ActivityFeed::merge([$first], [$second])->events)->toBe([$first, $second]);

    expect(ActivityFeed::merge([$second], [$first])->events)->toBe([$second, $first]);
});

it('is empty with no sources at all', function (): void {
    expect(ActivityFeed::merge()->isEmpty())->toBeTrue();
});

it('is empty with only empty sources', function (): void {
    expect(ActivityFeed::merge([], [])->isEmpty())->toBeTrue();
});

it('filters to one kind, preserving order', function (): void {
    $order = feedEventAt(new DateTimeImmutable('2026-08-21 09:00:00'), ActivityKind::Order);
    $shippingLate = feedEventAt(new DateTimeImmutable('2026-08-22 09:00:00'), ActivityKind::Shipping);
    $shippingEarly = feedEventAt(new DateTimeImmutable('2026-08-20 09:00:00'), ActivityKind::Shipping);

    $feed = ActivityFeed::merge([$order, $shippingLate, $shippingEarly]);

    expect($feed->filter(ActivityKind::Shipping)->events)->toBe([$shippingLate, $shippingEarly]);
});

it('reads the whole feed when the filter names no kind', function (): void {
    $order = feedEventAt(new DateTimeImmutable('2026-08-21 09:00:00'), ActivityKind::Order);
    $shipping = feedEventAt(new DateTimeImmutable('2026-08-20 09:00:00'), ActivityKind::Shipping);

    $feed = ActivityFeed::merge([$order, $shipping]);

    expect($feed->filter(null)->events)->toBe($feed->events);
});

it('is empty only when it holds no rows', function (): void {
    $feed = ActivityFeed::merge([feedEventAt(new DateTimeImmutable('2026-08-20 09:00:00'))]);

    expect($feed->isEmpty())->toBeFalse()
        ->and(ActivityFeed::merge()->isEmpty())->toBeTrue();
});
