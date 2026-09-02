<?php

declare(strict_types=1);

namespace App\Analytics;

use App\Domain\Identifiers\PrefixedId;
use DateTimeImmutable;
use DateTimeZone;

it('builds a listing event attributed to the customer who triggered it', function (): void {
    $at = new DateTimeImmutable('2026-08-22T14:32:00+00:00');

    $event = AnalyticsEvent::forListing(AnalyticsEventName::ListingFavorite, 'lst_ABC', 'cus_XYZ', $at, 'dedupe-key');

    expect($event->name)->toBe(AnalyticsEventName::ListingFavorite)
        ->and($event->occurredAt)->toBe($at)
        ->and($event->subjectType)->toBe('listing')
        ->and($event->subjectId)->toBe('lst_ABC')
        ->and($event->actorId)->toBe('cus_XYZ')
        ->and($event->dedupeKey)->toBe('dedupe-key')
        ->and($event->data)->toBe([]);
});

it('builds a listing event for an anonymous visitor with no actor', function (): void {
    $event = AnalyticsEvent::forListing(AnalyticsEventName::ListingView, 'lst_ABC', null, new DateTimeImmutable);

    expect($event->actorId)->toBeNull()
        ->and($event->dedupeKey)->toBeNull();
});

it('mints a row carrying every field, ready for insert', function (): void {
    $event = new AnalyticsEvent(
        name: AnalyticsEventName::ListingCartAdd,
        occurredAt: new DateTimeImmutable('2026-08-22T14:32:07+00:00'),
        subjectType: 'listing',
        subjectId: 'lst_ABC',
        actorId: 'cus_XYZ',
        dedupeKey: 'dedupe-key',
        data: ['quantity' => 2],
    );

    $columns = $event->columns();

    expect(PrefixedId::parse('aev', $columns['id']))->not->toBeNull()
        ->and($columns['name'])->toBe('listing.cart_add')
        ->and($columns['occurred_at'])->toBe('2026-08-22 14:32:07')
        ->and($columns['subject_type'])->toBe('listing')
        ->and($columns['subject_id'])->toBe('lst_ABC')
        ->and($columns['actor_id'])->toBe('cus_XYZ')
        ->and($columns['dedupe_key'])->toBe('dedupe-key')
        ->and($columns['data'])->toBe('{"quantity":2}');
});

it('mints a different id for every row, so a chunked insert never collides on the primary key', function (): void {
    $event = AnalyticsEvent::forListing(AnalyticsEventName::ListingView, 'lst_ABC', null, new DateTimeImmutable);

    expect($event->columns()['id'])->not->toBe($event->columns()['id']);
});

it('stamps occurred_at in UTC regardless of the timezone the moment carries', function (): void {
    $at = new DateTimeImmutable('2026-08-22T16:32:00', new DateTimeZone('+02:00'));

    $event = AnalyticsEvent::forListing(AnalyticsEventName::ListingView, 'lst_ABC', null, $at);

    expect($event->columns()['occurred_at'])->toBe('2026-08-22 14:32:00');
});
