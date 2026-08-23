<?php

declare(strict_types=1);

namespace App\Models;

use App\Actions\Listings\RecordListingEvent;
use App\Domain\Listings\ListingEventType;

it('counts a day of events by type in one row each', function (): void {
    $listing = $this->listing($this->seller());
    $record = app(RecordListingEvent::class);
    $record($listing, null, ListingEventType::View, $this->moment('2026-08-20 09:00:00'));
    $record($listing, null, ListingEventType::View, $this->moment('2026-08-20 21:00:00'));
    $record($listing, null, ListingEventType::Favorite, $this->moment('2026-08-21 09:00:00'));

    $rows = ListingEvent::query()->dailyCountsSince($this->moment('2026-08-01 00:00:00'))->get();

    expect($rows)->toHaveCount(2)
        ->and($listing->eventCountsByDateSince($this->moment('2026-08-01 00:00:00')))
        ->toBe([
            '2026-08-20' => [ListingEventType::View->value => 2],
            '2026-08-21' => [ListingEventType::Favorite->value => 1],
        ]);
});

it('reads the listing and customer it was recorded for', function (): void {
    $listing = $this->listing($this->seller());
    $customer = $this->anonymousCustomer();
    app(RecordListingEvent::class)($listing, $customer->id, ListingEventType::View, $this->moment('2026-08-20 09:00:00'));
    $event = $listing->events()->sole();

    expect($event->listing()->sole()->is($listing))->toBeTrue()
        ->and($event->customer?->is($customer))->toBeTrue();
});

it('leaves events before the window out', function (): void {
    $listing = $this->listing($this->seller());
    $record = app(RecordListingEvent::class);
    $record($listing, null, ListingEventType::View, $this->moment('2020-01-01 09:00:00'));
    $record($listing, null, ListingEventType::View, $this->moment('2026-08-20 09:00:00'));

    expect($listing->eventCountsByDateSince($this->moment('2026-08-15 00:00:00')))
        ->toBe(['2026-08-20' => [ListingEventType::View->value => 1]]);
});
