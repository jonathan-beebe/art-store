<?php

declare(strict_types=1);

namespace App\Analytics;

use DateTimeImmutable;
use Illuminate\Http\Request;

it('reads the landing path, referrer host, and utm parameters off the request', function (): void {
    $request = Request::create(
        'https://store.example.test/art/starry-night?utm_source=newsletter&utm_medium=email&utm_campaign=sept&utm_content=hero&utm_term=stars',
        server: ['HTTP_REFERER' => 'https://google.com/search'],
    );
    $facts = RequestFacts::of(null, 'ses_01J00000000000000000000ABC', null);
    $at = new DateTimeImmutable('2026-09-02T10:00:00+00:00');

    $visit = AnalyticsVisit::fromRequest($request, $facts, 'cus_XYZ', $at);

    expect($visit)->not->toBeNull()
        ->and($visit?->sessionId)->toBe('ses_01J00000000000000000000ABC')
        ->and($visit?->firstSeenAt)->toBe($at)
        ->and($visit?->landingPath)->toBe('/art/starry-night')
        ->and($visit?->referrerHost)->toBe('google.com')
        ->and($visit?->utmSource)->toBe('newsletter')
        ->and($visit?->utmMedium)->toBe('email')
        ->and($visit?->utmCampaign)->toBe('sept')
        ->and($visit?->utmContent)->toBe('hero')
        ->and($visit?->utmTerm)->toBe('stars')
        ->and($visit?->actorId)->toBe('cus_XYZ');
});

it('is null when the request carries no session', function (): void {
    $request = Request::create('https://store.example.test/');
    $facts = RequestFacts::of(null, null, null);

    expect(AnalyticsVisit::fromRequest($request, $facts, null, new DateTimeImmutable))->toBeNull();
});

it('stores no referrer host for a same-host referrer', function (): void {
    $request = Request::create(
        'https://store.example.test/art/starry-night',
        server: ['HTTP_REFERER' => 'https://store.example.test/'],
    );
    $facts = RequestFacts::of(null, 'ses_01J00000000000000000000ABC', null);

    $visit = AnalyticsVisit::fromRequest($request, $facts, null, new DateTimeImmutable);

    expect($visit?->referrerHost)->toBeNull();
});

it('stores no referrer host when the request carries none', function (): void {
    $request = Request::create('https://store.example.test/art/starry-night');
    $facts = RequestFacts::of(null, 'ses_01J00000000000000000000ABC', null);

    $visit = AnalyticsVisit::fromRequest($request, $facts, null, new DateTimeImmutable);

    expect($visit?->referrerHost)->toBeNull();
});

it('stores null for a utm parameter the request does not carry', function (): void {
    $request = Request::create('https://store.example.test/');
    $facts = RequestFacts::of(null, 'ses_01J00000000000000000000ABC', null);

    $visit = AnalyticsVisit::fromRequest($request, $facts, null, new DateTimeImmutable);

    expect($visit?->utmSource)->toBeNull()
        ->and($visit?->utmMedium)->toBeNull()
        ->and($visit?->utmCampaign)->toBeNull()
        ->and($visit?->utmContent)->toBeNull()
        ->and($visit?->utmTerm)->toBeNull();
});

it('caps a utm value at 255 characters', function (): void {
    $long = str_repeat('a', 300);
    $request = Request::create("https://store.example.test/?utm_source={$long}");
    $facts = RequestFacts::of(null, 'ses_01J00000000000000000000ABC', null);

    $visit = AnalyticsVisit::fromRequest($request, $facts, null, new DateTimeImmutable);

    expect($visit?->utmSource)->toHaveLength(255);
});

it('carries the row analytics_visits expects', function (): void {
    $request = Request::create(
        'https://store.example.test/art/starry-night?utm_source=newsletter',
        server: ['HTTP_REFERER' => 'https://google.com/search'],
    );
    $facts = RequestFacts::of(null, 'ses_01J00000000000000000000ABC', null);
    $at = new DateTimeImmutable('2026-09-02T10:00:00+00:00');

    $columns = AnalyticsVisit::fromRequest($request, $facts, 'cus_XYZ', $at)?->columns();

    expect($columns)->toBe([
        'session_id' => 'ses_01J00000000000000000000ABC',
        'first_seen_at' => '2026-09-02 10:00:00',
        'landing_path' => '/art/starry-night',
        'referrer_host' => 'google.com',
        'utm_source' => 'newsletter',
        'utm_medium' => null,
        'utm_campaign' => null,
        'utm_content' => null,
        'utm_term' => null,
        'actor_id' => 'cus_XYZ',
    ]);
});
