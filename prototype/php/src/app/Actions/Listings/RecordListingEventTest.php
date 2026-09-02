<?php

declare(strict_types=1);

namespace App\Actions\Listings;

use App\Domain\Listings\ListingEventType;
use App\Models\ListingEvent;
use Illuminate\Support\Facades\DB;
use Tests\CapturedStory;

it('records a view against the listing and the customer', function (): void {
    $listing = $this->listing($this->seller());
    $customer = $this->verifiedCustomer();

    $event = app(RecordListingEvent::class)($listing, $customer->id, ListingEventType::View, $this->moment('2026-08-20 09:00:00'));

    assert($event instanceof ListingEvent);
    expect($event->listing_id)->toBe($listing->id)
        ->and($event->customer_id)->toBe($customer->id)
        ->and($event->type)->toBe(ListingEventType::View)
        ->and($event->occurred_at->format('Y-m-d H:i:s'))->toBe('2026-08-20 09:00:00');
});

it('records an event with no customer behind it', function (): void {
    $listing = $this->listing($this->seller());

    $event = app(RecordListingEvent::class)($listing, null, ListingEventType::View, $this->moment('2026-08-20 09:00:00'));

    assert($event instanceof ListingEvent);
    expect($event->customer_id)->toBeNull();
});

it('counts the events a listing has collected', function (): void {
    $listing = $this->listing($this->seller());
    $customer = $this->verifiedCustomer();
    $record = app(RecordListingEvent::class);
    $now = $this->moment('2026-08-20 09:00:00');

    // The second view lands an hour after the first, so both are counted —
    // one within the same hour would collapse into the first (see the
    // dedicated collapse tests below).
    $record($listing, $customer->id, ListingEventType::View, $now);
    $record($listing, $customer->id, ListingEventType::View, $now->modify('+1 hour'));
    $record($listing, $customer->id, ListingEventType::Favorite, $now);
    $record($listing, $customer->id, ListingEventType::CartAdd, $now);

    $counted = $listing->loadEventCounts();

    expect($counted->views_count)->toBe(2)
        ->and($counted->favorites_count)->toBe(1)
        ->and($counted->cart_adds_count)->toBe(1);
});

it('collapses a second view inside the same hour into no row at all', function (): void {
    $listing = $this->listing($this->seller());
    $customer = $this->verifiedCustomer();
    $record = app(RecordListingEvent::class);

    $first = $record($listing, $customer->id, ListingEventType::View, $this->moment('2026-08-20 09:00:00'));
    $second = $record($listing, $customer->id, ListingEventType::View, $this->moment('2026-08-20 09:59:59'));

    expect($first)->not->toBeNull()
        ->and($second)->toBeNull()
        ->and($listing->events()->count())->toBe(1);
});

it('records a view in the next hour as a row of its own', function (): void {
    $listing = $this->listing($this->seller());
    $customer = $this->verifiedCustomer();
    $record = app(RecordListingEvent::class);

    $record($listing, $customer->id, ListingEventType::View, $this->moment('2026-08-20 09:00:00'));
    $second = $record($listing, $customer->id, ListingEventType::View, $this->moment('2026-08-20 10:00:00'));

    expect($second)->not->toBeNull()
        ->and($listing->events()->count())->toBe(2);
});

it('does not let one customer\'s view suppress another\'s in the same hour', function (): void {
    $listing = $this->listing($this->seller());
    $first = $this->verifiedCustomer();
    $second = $this->anonymousCustomer();
    $record = app(RecordListingEvent::class);
    $now = $this->moment('2026-08-20 09:00:00');

    $record($listing, $first->id, ListingEventType::View, $now);
    $recorded = $record($listing, $second->id, ListingEventType::View, $now);

    expect($recorded)->not->toBeNull()
        ->and($listing->events()->count())->toBe(2);
});

it('collapses a second anonymous view in the same hour', function (): void {
    $listing = $this->listing($this->seller());
    $record = app(RecordListingEvent::class);
    $now = $this->moment('2026-08-20 09:00:00');

    $record($listing, null, ListingEventType::View, $now);
    $recorded = $record($listing, null, ListingEventType::View, $now->modify('+10 minutes'));

    expect($recorded)->toBeNull()
        ->and($listing->events()->count())->toBe(1);
});

it('never collapses a favorite, unfavorite, or cart add, even inside the same hour', function (): void {
    $listing = $this->listing($this->seller());
    $customer = $this->verifiedCustomer();
    $record = app(RecordListingEvent::class);
    $now = $this->moment('2026-08-20 09:00:00');

    $record($listing, $customer->id, ListingEventType::Favorite, $now);
    $second = $record($listing, $customer->id, ListingEventType::Favorite, $now);

    expect($second)->not->toBeNull()
        ->and($listing->events()->where('type', ListingEventType::Favorite)->count())->toBe(2);
});

it('returns null and logs a warning instead of recording when the analytics store is unwritable', function (): void {
    $log = CapturedStory::capture();
    $listing = $this->listing($this->seller());
    $originalDatabase = config('database.connections.analytics.database');
    // RefreshDatabase already opened a transaction on this PDO for the
    // current test (tests/TestCase.php's connectionsToTransact); purging
    // the connection below drops the wrapper without closing it, so it is
    // rolled back by hand once the test is done with it — otherwise the
    // next test to begin a transaction on the same cached in-memory PDO
    // finds one already open.
    $originalPdo = DB::connection('analytics')->getPdo();

    config()->set('database.connections.analytics.database', '/nonexistent/dir/analytics.sqlite3');
    DB::purge('analytics');

    try {
        $event = app(RecordListingEvent::class)($listing, null, ListingEventType::View, $this->moment('2026-08-20 09:00:00'));

        expect($event)->toBeNull()
            ->and($log->line('app.log', 'doing')['level'])->toBe('warn');
    } finally {
        if ($originalPdo->inTransaction()) {
            $originalPdo->rollBack();
        }

        config()->set('database.connections.analytics.database', $originalDatabase);
        DB::purge('analytics');
    }
});
