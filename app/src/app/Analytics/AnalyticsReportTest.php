<?php

declare(strict_types=1);

namespace App\Analytics;

use App\Domain\Analytics\AnalyticsEventName;
use App\Logging\RequestMarks;
use DateTimeImmutable;
use Illuminate\Http\Request;

function recordListingEvent(Analytics $analytics, AnalyticsEventName $name, string $listingId, DateTimeImmutable $at): void
{
    $analytics->recordEvent(AnalyticsEvent::forListing($name, $listingId, 'cus_XYZ', $at));
}

/**
 * Binds a request carrying the given ip, session, and request id as the
 * container's current one, then records the event through it — the shape
 * every {@see AnalyticsReport::eventsForIp()}/{@see AnalyticsReport::eventsForSession()}
 * test needs, since `Analytics::recordEvent()` reads the request from the
 * container rather than taking it as an argument.
 */
function recordFromRequest(Analytics $analytics, AnalyticsEvent $event, string $ip, string $sessionId, string $requestId): void
{
    $request = Request::create('/', server: ['REMOTE_ADDR' => $ip]);
    $request->attributes->set(RequestMarks::REQUEST_ID_ATTRIBUTE, $requestId);
    $request->cookies->set(RequestMarks::SESSION_COOKIE, $sessionId);
    app()->instance('request', $request);

    $analytics->recordEvent($event);
}

it('tallies one listing\'s views, favorites, and cart adds, leaving another listing out', function (): void {
    $analytics = new Analytics;
    $at = new DateTimeImmutable('2026-08-22T14:00:00+00:00');

    recordListingEvent($analytics, AnalyticsEventName::ListingView, 'lst_ABC', $at);
    recordListingEvent($analytics, AnalyticsEventName::ListingView, 'lst_ABC', $at->modify('+1 hour'));
    recordListingEvent($analytics, AnalyticsEventName::ListingFavorite, 'lst_ABC', $at);
    recordListingEvent($analytics, AnalyticsEventName::ListingCartAdd, 'lst_ABC', $at);
    recordListingEvent($analytics, AnalyticsEventName::ListingView, 'lst_OTHER', $at);
    $analytics->flush();

    $counts = AnalyticsReport::countsForListing('lst_ABC');

    expect($counts->views)->toBe(2)
        ->and($counts->favorites)->toBe(1)
        ->and($counts->cartAdds)->toBe(1);
});

it('tallies zero for a listing with no recorded events', function (): void {
    $counts = AnalyticsReport::countsForListing('lst_ABC');

    expect($counts->views)->toBe(0)
        ->and($counts->favorites)->toBe(0)
        ->and($counts->cartAdds)->toBe(0);
});

it('tallies several listings since a cutoff, keyed by id, leaving events before it out', function (): void {
    $analytics = new Analytics;
    $at = new DateTimeImmutable('2026-08-22T14:00:00+00:00');

    recordListingEvent($analytics, AnalyticsEventName::ListingView, 'lst_ABC', $at);
    recordListingEvent($analytics, AnalyticsEventName::ListingView, 'lst_ABC', $at->modify('+1 hour'));
    recordListingEvent($analytics, AnalyticsEventName::ListingFavorite, 'lst_ABC', $at);
    recordListingEvent($analytics, AnalyticsEventName::ListingCartAdd, 'lst_DEF', $at);
    recordListingEvent($analytics, AnalyticsEventName::ListingView, 'lst_ABC', $at->modify('-1 day'));
    recordListingEvent($analytics, AnalyticsEventName::ListingView, 'lst_OTHER', $at);
    $analytics->flush();

    $counts = AnalyticsReport::countsForListingsSince(['lst_ABC', 'lst_DEF'], $at->modify('-1 hour'));

    expect($counts)->toHaveCount(2)
        ->and($counts['lst_ABC']->views)->toBe(2)
        ->and($counts['lst_ABC']->favorites)->toBe(1)
        ->and($counts['lst_ABC']->cartAdds)->toBe(0)
        ->and($counts['lst_DEF']->views)->toBe(0)
        ->and($counts['lst_DEF']->cartAdds)->toBe(1);
});

it('tallies zero for every listing when none has recorded an event', function (): void {
    $counts = AnalyticsReport::countsForListingsSince(['lst_ABC', 'lst_DEF'], new DateTimeImmutable('2026-08-01T00:00:00+00:00'));

    expect($counts)->toHaveCount(2)
        ->and($counts['lst_ABC']->views)->toBe(0)
        ->and($counts['lst_DEF']->views)->toBe(0);
});

it('tallies nothing for an empty list of listings', function (): void {
    expect(AnalyticsReport::countsForListingsSince([], new DateTimeImmutable('2026-08-01T00:00:00+00:00')))->toBe([]);
});

it('groups a listing\'s events by day and name from a cutoff onward', function (): void {
    $analytics = new Analytics;

    recordListingEvent($analytics, AnalyticsEventName::ListingView, 'lst_ABC', new DateTimeImmutable('2026-08-20T10:00:00+00:00'));
    recordListingEvent($analytics, AnalyticsEventName::ListingView, 'lst_ABC', new DateTimeImmutable('2026-08-22T10:00:00+00:00'));
    recordListingEvent($analytics, AnalyticsEventName::ListingFavorite, 'lst_ABC', new DateTimeImmutable('2026-08-22T11:00:00+00:00'));
    $analytics->flush();

    $counts = AnalyticsReport::dailyCountsForListingSince('lst_ABC', new DateTimeImmutable('2026-08-21T00:00:00+00:00'));

    expect($counts)->toHaveCount(1)
        ->and($counts['2026-08-22'][AnalyticsEventName::ListingView->value])->toBe(1)
        ->and($counts['2026-08-22'][AnalyticsEventName::ListingFavorite->value])->toBe(1);
});

it('lists everything one ip did since a cutoff, newest first, leaving another ip out', function (): void {
    $analytics = new Analytics;

    recordFromRequest(
        $analytics,
        AnalyticsEvent::forListing(AnalyticsEventName::ListingView, 'lst_ABC', 'cus_XYZ', new DateTimeImmutable('2026-08-22T10:00:00+00:00')),
        '203.0.113.9',
        'ses_01J00000000000000000000ABC',
        'req_01J00000000000000000000ONE',
    );
    recordFromRequest(
        $analytics,
        AnalyticsEvent::forListing(AnalyticsEventName::ListingCartAdd, 'lst_ABC', 'cus_XYZ', new DateTimeImmutable('2026-08-22T11:00:00+00:00')),
        '203.0.113.9',
        'ses_01J00000000000000000000ABC',
        'req_01J00000000000000000000TWO',
    );
    recordFromRequest(
        $analytics,
        AnalyticsEvent::forListing(AnalyticsEventName::ListingView, 'lst_ABC', 'cus_XYZ', new DateTimeImmutable('2026-08-22T12:00:00+00:00')),
        '198.51.100.4',
        'ses_01J00000000000000000000XYZ',
        'req_01J00000000000000000000OTH',
    );
    $analytics->flush();

    $rows = AnalyticsReport::eventsForIp('203.0.113.9', new DateTimeImmutable('2026-08-21T00:00:00+00:00'));

    expect($rows)->toHaveCount(2)
        ->and($rows[0]->name)->toBe(AnalyticsEventName::ListingCartAdd->value)
        ->and($rows[1]->name)->toBe(AnalyticsEventName::ListingView->value);

    $row = $rows[0];

    expect($row->subjectType)->toBe('listing')
        ->and($row->subjectId)->toBe('lst_ABC')
        ->and($row->actorId)->toBe('cus_XYZ')
        ->and($row->ip)->toBe('203.0.113.9')
        ->and($row->sessionId)->toBe('ses_01J00000000000000000000ABC')
        ->and($row->requestId)->toBe('req_01J00000000000000000000TWO')
        ->and($row->occurredAt)->toEqual(new DateTimeImmutable('2026-08-22T11:00:00+00:00'));
});

it('lists nothing for an ip with no events at or after the cutoff', function (): void {
    $analytics = new Analytics;

    recordFromRequest(
        $analytics,
        AnalyticsEvent::forListing(AnalyticsEventName::ListingView, 'lst_ABC', 'cus_XYZ', new DateTimeImmutable('2026-08-10T10:00:00+00:00')),
        '203.0.113.9',
        'ses_01J00000000000000000000ABC',
        'req_01J00000000000000000000ONE',
    );
    $analytics->flush();

    $rows = AnalyticsReport::eventsForIp('203.0.113.9', new DateTimeImmutable('2026-08-21T00:00:00+00:00'));

    expect($rows)->toBe([]);
});

it('lists everything one session did since a cutoff, newest first, across whichever ip each request used', function (): void {
    $analytics = new Analytics;

    recordFromRequest(
        $analytics,
        AnalyticsEvent::forListing(AnalyticsEventName::ListingView, 'lst_ABC', 'cus_XYZ', new DateTimeImmutable('2026-08-22T10:00:00+00:00')),
        '203.0.113.9',
        'ses_01J00000000000000000000ABC',
        'req_01J00000000000000000000ONE',
    );
    recordFromRequest(
        $analytics,
        AnalyticsEvent::forListing(AnalyticsEventName::ListingFavorite, 'lst_ABC', 'cus_XYZ', new DateTimeImmutable('2026-08-22T11:00:00+00:00')),
        // A different ip, the same session — a roaming visitor's requests
        // still join up by session even when the ip changes between them.
        '198.51.100.4',
        'ses_01J00000000000000000000ABC',
        'req_01J00000000000000000000TWO',
    );
    recordFromRequest(
        $analytics,
        AnalyticsEvent::forListing(AnalyticsEventName::ListingView, 'lst_ABC', 'cus_XYZ', new DateTimeImmutable('2026-08-22T12:00:00+00:00')),
        '203.0.113.9',
        'ses_01J00000000000000000000XYZ',
        'req_01J00000000000000000000OTH',
    );
    $analytics->flush();

    $rows = AnalyticsReport::eventsForSession('ses_01J00000000000000000000ABC', new DateTimeImmutable('2026-08-21T00:00:00+00:00'));

    expect($rows)->toHaveCount(2)
        ->and($rows[0]->name)->toBe(AnalyticsEventName::ListingFavorite->value)
        ->and($rows[0]->ip)->toBe('198.51.100.4')
        ->and($rows[1]->name)->toBe(AnalyticsEventName::ListingView->value)
        ->and($rows[1]->ip)->toBe('203.0.113.9');
});

it('lists an actor\'s own visits, newest first, each with its channel and referrer', function (): void {
    $analytics = new Analytics;

    $analytics->recordVisit(new AnalyticsVisit(
        'ses_01J00000000000000000000ONE',
        new DateTimeImmutable('2026-08-20T09:00:00+00:00'),
        '/art/starry-night',
        'newsletter.example.com',
        'newsletter',
        'email',
        'sept',
        null,
        null,
        'cus_XYZ',
    ));
    $analytics->recordVisit(new AnalyticsVisit(
        'ses_01J00000000000000000000TWO',
        new DateTimeImmutable('2026-08-21T09:00:00+00:00'),
        '/',
        null,
        null,
        null,
        null,
        null,
        null,
        'cus_XYZ',
    ));
    $analytics->recordVisit(new AnalyticsVisit(
        'ses_01J00000000000000000000OTH',
        new DateTimeImmutable('2026-08-22T09:00:00+00:00'),
        '/',
        null,
        null,
        null,
        null,
        null,
        null,
        'cus_OTHER',
    ));
    $analytics->flush();

    $rows = AnalyticsReport::visitsForActor('cus_XYZ');

    expect($rows)->toHaveCount(2)
        ->and($rows[0]->sessionId)->toBe('ses_01J00000000000000000000TWO')
        ->and($rows[0]->channel->key)->toBe('direct')
        ->and($rows[0]->referrerHost)->toBeNull()
        ->and($rows[1]->sessionId)->toBe('ses_01J00000000000000000000ONE')
        ->and($rows[1]->landingPath)->toBe('/art/starry-night')
        ->and($rows[1]->channel->key)->toBe('campaign:sept')
        ->and($rows[1]->referrerHost)->toBe('newsletter.example.com');
});

it('lists no visits for an actor who never carried one', function (): void {
    expect(AnalyticsReport::visitsForActor('cus_XYZ'))->toBe([]);
});
