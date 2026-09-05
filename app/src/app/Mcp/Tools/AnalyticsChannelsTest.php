<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use App\Mcp\AdminServer;
use Illuminate\Testing\Fluent\AssertableJson;
use Tests\AnalyticsStoreFixtures;

it('answers each channel with its five metrics against the range before', function (): void {
    AnalyticsStoreFixtures::seedDirectVisit();

    AdminServer::tool(AnalyticsChannels::class, ['days' => 90])
        ->assertOk()
        ->assertStructuredContent(fn (AssertableJson $json) => $json
            ->where('range.days', 90)
            ->where('channels.0.channel', 'Direct')
            ->where('channels.0.visitors.current', 1)
            ->has('channels.0.orders_paid.change')
            ->etc());
});
