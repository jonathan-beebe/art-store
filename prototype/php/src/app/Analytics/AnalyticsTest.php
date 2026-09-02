<?php

declare(strict_types=1);

namespace App\Analytics;

use App\Domain\Analytics\PageViewSite;
use App\Models\PageViewCount;
use DateTimeImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Tests\AnalyticsStoreFixtures;
use Tests\CapturedStory;

function listingViewedAt(DateTimeImmutable $at, ?string $dedupeKey = null): AnalyticsEvent
{
    return AnalyticsEvent::forListing(AnalyticsEventName::ListingView, 'lst_ABC', 'cus_XYZ', $at, $dedupeKey);
}

it('buffers a recorded event without writing it', function (): void {
    $analytics = new Analytics;

    $analytics->recordEvent(listingViewedAt(new DateTimeImmutable));

    expect($analytics->pending())->toBe(1)
        ->and(DB::connection('analytics')->table('analytics_events')->count())->toBe(0);
});

it('writes the buffered event on flush, carrying the moment it was recorded', function (): void {
    $analytics = new Analytics;
    $at = new DateTimeImmutable('2026-08-22T14:32:07+00:00');

    $analytics->recordEvent(listingViewedAt($at));
    $analytics->flush();

    $row = DB::connection('analytics')->table('analytics_events')->sole();

    expect($analytics->pending())->toBe(0)
        ->and($row->id)->toStartWith('aev_')
        ->and($row->name)->toBe(AnalyticsEventName::ListingView->value)
        ->and($row->occurred_at)->toBe('2026-08-22 14:32:07')
        ->and($row->subject_type)->toBe('listing')
        ->and($row->subject_id)->toBe('lst_ABC')
        ->and($row->actor_id)->toBe('cus_XYZ');
});

it('does nothing when flushed with an empty buffer', function (): void {
    (new Analytics)->flush();

    expect(DB::connection('analytics')->table('analytics_events')->count())->toBe(0);
});

it('ignores a second event carrying a dedupe key already written', function (): void {
    $analytics = new Analytics;
    $dedupeKey = 'listing:lst_ABC:customer:cus_XYZ:hour:2026-08-22T14';

    $analytics->recordEvent(listingViewedAt(new DateTimeImmutable('2026-08-22T14:05:00+00:00'), $dedupeKey));
    $analytics->flush();
    $analytics->recordEvent(listingViewedAt(new DateTimeImmutable('2026-08-22T14:40:00+00:00'), $dedupeKey));
    $analytics->flush();

    expect(DB::connection('analytics')->table('analytics_events')->count())->toBe(1);
});

it('rolls two page views of the same pattern up into one upsert', function (): void {
    $analytics = new Analytics;
    $at = new DateTimeImmutable('2026-08-22T14:00:00+00:00');

    $analytics->recordPageView(PageViewSite::Shop, '/art/{listing}', $at);
    $analytics->recordPageView(PageViewSite::Shop, '/art/{listing}', $at);
    $analytics->flush();

    $row = PageViewCount::query()->sole();

    expect($row->site)->toBe(PageViewSite::Shop->value)
        ->and($row->path_pattern)->toBe('/art/{listing}')
        ->and($row->day)->toBe('2026-08-22')
        ->and($row->count)->toBe(2);
});

it('increments an existing page-view row rather than inserting a new one', function (): void {
    PageViewCount::factory()->create([
        'site' => PageViewSite::Shop->value,
        'path_pattern' => '/art/{listing}',
        'day' => '2026-08-22',
        'count' => 5,
    ]);

    $analytics = new Analytics;
    $analytics->recordPageView(PageViewSite::Shop, '/art/{listing}', new DateTimeImmutable('2026-08-22T14:00:00+00:00'));
    $analytics->flush();

    expect(PageViewCount::query()->sole()->count)->toBe(6);
});

it('counts a page view toward pending() once per distinct key, not once per hit', function (): void {
    $analytics = new Analytics;
    $at = new DateTimeImmutable('2026-08-22T14:00:00+00:00');

    $analytics->recordPageView(PageViewSite::Shop, '/art/{listing}', $at);
    $analytics->recordPageView(PageViewSite::Shop, '/art/{listing}', $at);
    $analytics->recordPageView(PageViewSite::Seller, '/seller', $at);

    expect($analytics->pending())->toBe(2);
});

it('flushes automatically at the row cap', function (): void {
    $analytics = new Analytics;

    for ($i = 0; $i < 255; $i++) {
        $analytics->recordEvent(listingViewedAt(new DateTimeImmutable));
    }

    expect(DB::connection('analytics')->table('analytics_events')->count())->toBe(0);

    $analytics->recordEvent(listingViewedAt(new DateTimeImmutable));

    expect($analytics->pending())->toBe(0)
        ->and(DB::connection('analytics')->table('analytics_events')->count())->toBe(256);
});

it('logs one warning and drops the batch when the store cannot be written to', function (): void {
    $log = CapturedStory::capture();

    AnalyticsStoreFixtures::withUnwritableStore(function () use ($log): void {
        $analytics = new Analytics;
        $analytics->recordEvent(listingViewedAt(new DateTimeImmutable));

        $analytics->flush();

        $line = $log->line('app.log', 'doing');

        expect($analytics->pending())->toBe(0)
            ->and($line['level'])->toBe('warn')
            ->and($line['data'])->toBe([
                'analytics_database_file' => config('database.connections.analytics.database'),
                'events' => 1,
            ]);
    });
});

it('moves every row an actor owns to another actor', function (): void {
    $analytics = new Analytics;
    $analytics->recordEvent(listingViewedAt(new DateTimeImmutable));
    $analytics->flush();

    $analytics->reassignActor('cus_XYZ', 'cus_MERGED');

    expect(DB::connection('analytics')->table('analytics_events')->sole()->actor_id)->toBe('cus_MERGED');
});

it('logs one warning and never throws when reassignActor cannot write', function (): void {
    $log = CapturedStory::capture();

    AnalyticsStoreFixtures::withUnwritableStore(function () use ($log): void {
        (new Analytics)->reassignActor('cus_XYZ', 'cus_MERGED');

        expect($log->line('app.log', 'doing')['level'])->toBe('warn');
    });
});

it('flushes what a request recorded once the response has already gone back', function (): void {
    Route::get('/analytics-test-route', function (): string {
        app(Analytics::class)->recordEvent(listingViewedAt(new DateTimeImmutable));

        return 'ok';
    })->middleware('web');

    $this->get('/analytics-test-route')->assertOk();

    expect(DB::connection('analytics')->table('analytics_events')->count())->toBe(1);
});
