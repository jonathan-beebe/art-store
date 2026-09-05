<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use App\Analytics\Analytics;
use App\Analytics\AnalyticsEvent;
use App\Domain\Analytics\AnalyticsEventName;
use App\Mcp\AdminServer;
use Illuminate\Testing\Fluent\AssertableJson;

it('answers every event name over the range with counts against the range before', function (): void {
    $listing = $this->listing($this->seller());
    $customer = $this->verifiedCustomer();
    $analytics = app(Analytics::class);
    $analytics->recordEvent(AnalyticsEvent::forListing(AnalyticsEventName::ListingView, $listing->id, $customer->id, $this->moment('2026-08-23 09:00:00')));
    $analytics->recordEvent(AnalyticsEvent::forListing(AnalyticsEventName::ListingView, $listing->id, $customer->id, $this->moment('2026-08-15 09:00:00')));
    $analytics->flush();

    AdminServer::tool(AnalyticsEvents::class, ['days' => 7, 'ends_on' => '2026-08-24'])
        ->assertOk()
        ->assertStructuredContent(fn (AssertableJson $json) => $json
            ->where('range.days', 7)
            ->where('range.start', '2026-08-18T00:00:00Z')
            ->where('events.0.name', 'listing.view')
            ->where('events.0.current', 1)
            ->where('events.0.previous', 1)
            ->has('events.0.daily', 7)
            ->etc());
});

it('narrows by q and refuses a range size the admin site does not offer', function (): void {
    AdminServer::tool(AnalyticsEvents::class, ['q' => 'favorite', 'ends_on' => '2026-08-24'])
        ->assertOk()
        ->assertStructuredContent(fn (AssertableJson $json) => $json
            ->has('events', 2)
            ->where('events.0.name', 'listing.favorite')
            ->etc());

    AdminServer::tool(AnalyticsEvents::class, ['days' => 10])->assertHasErrors();
});
