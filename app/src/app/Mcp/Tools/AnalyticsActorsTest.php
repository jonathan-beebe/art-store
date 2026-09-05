<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use App\Analytics\Analytics;
use App\Analytics\AnalyticsEvent;
use App\Domain\Analytics\AnalyticsEventName;
use App\Mcp\AdminServer;
use Illuminate\Testing\Fluent\AssertableJson;

it('answers the active customers over the range, most active first, with paging', function (): void {
    $listing = $this->listing($this->seller());
    $busy = $this->verifiedCustomer();
    $quiet = $this->verifiedCustomer();
    $analytics = app(Analytics::class);

    foreach (range(1, 3) as $i) {
        $analytics->recordEvent(AnalyticsEvent::forListing(AnalyticsEventName::ListingView, $listing->id, $busy->id, $this->moment("2026-08-19 09:0{$i}:00")));
    }
    $analytics->recordEvent(AnalyticsEvent::forListing(AnalyticsEventName::ListingView, $listing->id, $quiet->id, $this->moment('2026-08-20 09:00:00')));
    $analytics->flush();

    AdminServer::tool(AnalyticsActors::class, ['days' => 7, 'ends_on' => '2026-08-24'])
        ->assertOk()
        ->assertStructuredContent(fn (AssertableJson $json) => $json
            ->where('page.number', 1)
            ->where('page.total_count', 2)
            ->where('page.is_last', true)
            ->has('actors', 2)
            ->where('actors.0.id', $busy->id)
            ->where('actors.0.events', 3)
            ->where('actors.0.kind', 'verified')
            ->where('actors.1.id', $quiet->id)
            ->etc());

    AdminServer::tool(AnalyticsActors::class, ['days' => 7, 'ends_on' => '2026-08-24', 'sort' => 'recent'])
        ->assertOk()
        ->assertStructuredContent(fn (AssertableJson $json) => $json
            ->where('actors.0.id', $quiet->id)
            ->etc());
});

it('refuses a sort or kind outside the vocabulary', function (): void {
    AdminServer::tool(AnalyticsActors::class, ['sort' => 'loudest'])->assertHasErrors();
    AdminServer::tool(AnalyticsActors::class, ['kind' => 'robots'])->assertHasErrors();
});
