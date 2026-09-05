<?php

declare(strict_types=1);

namespace App\Analytics\Admin;

use App\Analytics\Analytics;
use App\Analytics\AnalyticsVisit;
use App\Domain\Analytics\AnalyticsRange;

it('lists a channel\'s own visits in the range, newest first', function (): void {
    $range = AnalyticsRange::of(7, $this->moment('2026-08-24 12:00:00'));
    $analytics = app(Analytics::class);

    $analytics->recordVisit(new AnalyticsVisit('sess-a', $this->moment('2026-08-19 09:00:00'), '/', null, null, null, null, null, null, null));
    $analytics->recordVisit(new AnalyticsVisit('sess-b', $this->moment('2026-08-20 09:00:00'), '/art/starry-night', null, null, null, null, null, null, null));
    $analytics->recordVisit(new AnalyticsVisit('sess-campaign', $this->moment('2026-08-19 09:00:00'), '/', null, 'newsletter', 'email', 'sept', null, null, null));
    $analytics->flush();

    $page = ChannelVisits::forRange($range, 'direct', 1);
    assert($page instanceof ChannelVisitsPage);

    expect($page->label)->toBe('Direct')
        ->and($page->page->totalCount)->toBe(2)
        ->and(array_column($page->rows, 'sessionId'))->toBe(['sess-b', 'sess-a']);
});

it('carries the visitor\'s actor id when the visit has one', function (): void {
    $range = AnalyticsRange::of(7, $this->moment('2026-08-24 12:00:00'));
    $analytics = app(Analytics::class);

    $analytics->recordVisit(new AnalyticsVisit('sess-a', $this->moment('2026-08-19 09:00:00'), '/', null, null, null, null, null, null, 'cus_ABC'));
    $analytics->flush();

    $page = ChannelVisits::forRange($range, 'direct', 1);
    assert($page instanceof ChannelVisitsPage);

    expect($page->rows[0]->actorId)->toBe('cus_ABC');
});

it('answers null for a key nothing in the range derives to', function (): void {
    $range = AnalyticsRange::of(7, $this->moment('2026-08-24 12:00:00'));
    $analytics = app(Analytics::class);

    $analytics->recordVisit(new AnalyticsVisit('sess-a', $this->moment('2026-08-19 09:00:00'), '/', null, null, null, null, null, null, null));
    $analytics->flush();

    expect(ChannelVisits::forRange($range, 'search:google', 1))->toBeNull();
});

it('answers null when nothing was recorded at all', function (): void {
    $range = AnalyticsRange::of(7, $this->moment('2026-08-24 12:00:00'));

    expect(ChannelVisits::forRange($range, 'direct', 1))->toBeNull();
});

it('excludes a visit outside the range', function (): void {
    $range = AnalyticsRange::of(7, $this->moment('2026-08-24 12:00:00'));
    $analytics = app(Analytics::class);

    $analytics->recordVisit(new AnalyticsVisit('sess-old', $this->moment('2026-08-01 09:00:00'), '/', null, null, null, null, null, null, null));
    $analytics->flush();

    expect(ChannelVisits::forRange($range, 'direct', 1))->toBeNull();
});

it('pages the matched visits', function (): void {
    $range = AnalyticsRange::of(7, $this->moment('2026-08-24 12:00:00'));
    $analytics = app(Analytics::class);

    foreach (range(1, 3) as $i) {
        $analytics->recordVisit(new AnalyticsVisit("sess-{$i}", $this->moment("2026-08-19 09:0{$i}:00"), '/', null, null, null, null, null, null, null));
    }
    $analytics->flush();

    $page = ChannelVisits::forRange($range, 'direct', 1, perPage: 2);
    assert($page instanceof ChannelVisitsPage);

    expect($page->rows)->toHaveCount(2)
        ->and($page->page->count)->toBe(2)
        ->and($page->page->totalCount)->toBe(3);
});
