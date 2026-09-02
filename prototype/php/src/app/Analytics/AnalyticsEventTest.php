<?php

declare(strict_types=1);

namespace App\Analytics;

use App\Domain\Analytics\AnalyticsEventName;
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
        ip: '203.0.113.9',
        sessionId: 'ses_01J00000000000000000000ABC',
    );

    $columns = $event->columns();

    expect(PrefixedId::parse('aev', $columns['id']))->not->toBeNull()
        ->and($columns['name'])->toBe('listing.cart_add')
        ->and($columns['occurred_at'])->toBe('2026-08-22 14:32:07')
        ->and($columns['subject_type'])->toBe('listing')
        ->and($columns['subject_id'])->toBe('lst_ABC')
        ->and($columns['actor_id'])->toBe('cus_XYZ')
        ->and($columns['ip'])->toBe('203.0.113.9')
        ->and($columns['session_id'])->toBe('ses_01J00000000000000000000ABC')
        ->and($columns['dedupe_key'])->toBe('dedupe-key')
        ->and($columns['data'])->toBe('{"quantity":2}');
});

it('carries no ip or session when neither is given', function (): void {
    $event = AnalyticsEvent::forListing(AnalyticsEventName::ListingView, 'lst_ABC', null, new DateTimeImmutable);

    expect($event->ip)->toBeNull()
        ->and($event->sessionId)->toBeNull()
        ->and($event->columns()['ip'])->toBeNull()
        ->and($event->columns()['session_id'])->toBeNull();
});

it('takes on a request\'s ip, session, and request id', function (): void {
    $event = AnalyticsEvent::forListing(AnalyticsEventName::ListingView, 'lst_ABC', 'cus_XYZ', new DateTimeImmutable);
    $facts = RequestFacts::of('203.0.113.9', 'ses_01J00000000000000000000ABC', 'req_01J00000000000000000000ABC');

    $withFacts = $event->withRequestFacts($facts);

    expect($withFacts->ip)->toBe('203.0.113.9')
        ->and($withFacts->sessionId)->toBe('ses_01J00000000000000000000ABC')
        ->and($withFacts->data)->toBe(['request_id' => 'req_01J00000000000000000000ABC'])
        ->and($withFacts->columns()['data'])->toBe('{"request_id":"req_01J00000000000000000000ABC"}')
        // The event this was built from carries none of it — withRequestFacts()
        // returns a new event rather than mutating the one it was called on.
        ->and($event->ip)->toBeNull();
});

it('leaves data as recorded when the request carries no request id', function (): void {
    $event = AnalyticsEvent::forListing(AnalyticsEventName::ListingView, 'lst_ABC', 'cus_XYZ', new DateTimeImmutable);

    $withFacts = $event->withRequestFacts(RequestFacts::of(null, null, null));

    expect($withFacts->data)->toBe([]);
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
