<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use App\Analytics\Analytics;
use App\Analytics\AnalyticsEvent;
use App\Domain\Analytics\AnalyticsEventName;
use App\Logging\RequestMarks;
use App\Mcp\AdminServer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Illuminate\Testing\Fluent\AssertableJson;

function recordTracedEvent(Analytics $analytics, string $ip, string $sessionId, string $at): void
{
    $request = Request::create('/', server: ['REMOTE_ADDR' => $ip]);
    $request->attributes->set(RequestMarks::REQUEST_ID_ATTRIBUTE, 'req_'.substr(md5($at), 0, 8));
    $request->cookies->set(RequestMarks::SESSION_COOKIE, $sessionId);
    app()->instance('request', $request);

    $analytics->recordEvent(AnalyticsEvent::forListing(AnalyticsEventName::ListingView, 'lst_ABC', 'cus_XYZ', test()->moment($at)));
}

beforeEach(function (): void {
    Date::setTestNow('2026-08-24 12:00:00');
    $analytics = new Analytics;
    recordTracedEvent($analytics, '203.0.113.9', 'ses_01J00000000000000000000ABC', '2026-08-22 10:00:00');
    recordTracedEvent($analytics, '203.0.113.9', 'ses_01J00000000000000000000ABC', '2026-08-22 11:00:00');
    recordTracedEvent($analytics, '198.51.100.4', 'ses_01J00000000000000000000XYZ', '2026-08-22 12:00:00');
    recordTracedEvent($analytics, '203.0.113.9', 'ses_01J00000000000000000000OLD', '2026-06-01 12:00:00');
    $analytics->flush();
});

afterEach(fn () => Date::setTestNow());

it('follows one session, newest first', function (): void {
    AdminServer::tool(TraceAnalytics::class, ['session_id' => 'ses_01J00000000000000000000ABC'])
        ->assertOk()
        ->assertStructuredContent(fn (AssertableJson $json) => $json
            ->where('session_id', 'ses_01J00000000000000000000ABC')
            ->where('ip', null)
            ->where('since', '2026-07-25T12:00:00Z')
            ->has('events', 2)
            ->where('events.0.occurred_at', '2026-08-22T11:00:00Z')
            ->where('events.0.ip', '203.0.113.9')
            ->etc());
});

it('follows one ip within the window', function (): void {
    AdminServer::tool(TraceAnalytics::class, ['ip' => '203.0.113.9', 'days' => 7])
        ->assertOk()
        ->assertStructuredContent(fn (AssertableJson $json) => $json
            ->where('ip', '203.0.113.9')
            ->has('events', 2)
            ->etc());
});

it('wants exactly one of session_id or ip, each well-formed', function (): void {
    AdminServer::tool(TraceAnalytics::class, [])->assertHasErrors([TraceAnalytics::ONE_OF]);
    AdminServer::tool(TraceAnalytics::class, ['ip' => '203.0.113.9', 'session_id' => 'ses_01J00000000000000000000ABC'])->assertHasErrors([TraceAnalytics::ONE_OF]);
    AdminServer::tool(TraceAnalytics::class, ['ip' => 'not-an-ip'])->assertHasErrors();
    AdminServer::tool(TraceAnalytics::class, ['session_id' => 'cus_01J00000000000000000000ABC'])->assertHasErrors();
});
