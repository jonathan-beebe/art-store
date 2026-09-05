<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use App\Logging\Admin\LogFilterInput;
use App\Logging\LogStore;
use App\Mcp\AdminServer;
use Illuminate\Testing\Fluent\AssertableJson;
use Tests\LogViewerFixtures as Fixtures;

beforeEach(function (): void {
    $this->app->instance(LogStore::class, Fixtures::store([
        Fixtures::line(['ts' => '2026-08-24T12:00:00.000Z', 'msg' => 'placed the order', 'request_id' => 'req_a', 'data' => ['order_id' => 'ord_1']]),
        Fixtures::line(['ts' => '2026-08-24T12:00:01.000Z', 'msg' => 'the card was declined', 'level' => 'error', 'event' => 'order.pay', 'phase' => 'failed', 'request_id' => 'req_a']),
        Fixtures::line(['ts' => '2026-08-24T12:00:02.000Z', 'msg' => 'a seller line', 'event' => 'listing.update', 'request_id' => 'req_b']),
    ]));
});

it('answers matching rows newest first with the total, decoding data', function (): void {
    AdminServer::tool(SearchLogs::class, ['request' => 'req_a'])
        ->assertOk()
        ->assertStructuredContent(fn (AssertableJson $json) => $json
            ->where('total', 2)
            ->where('limit', 50)
            ->where('offset', 0)
            ->has('rows', 2)
            ->where('rows.0.msg', 'the card was declined')
            ->where('rows.0.level', 'error')
            ->where('rows.1.data.order_id', 'ord_1')
            ->etc());
});

it('pages with limit and offset and reads a blank filter as absent', function (): void {
    AdminServer::tool(SearchLogs::class, ['limit' => 1, 'offset' => 1, 'event' => ''])
        ->assertOk()
        ->assertStructuredContent(fn (AssertableJson $json) => $json
            ->where('total', 3)
            ->where('limit', 1)
            ->where('offset', 1)
            ->has('rows', 1)
            ->where('rows.0.msg', 'the card was declined')
            ->etc());
});

it('refuses a value outside the vocabulary and a value without a key', function (): void {
    AdminServer::tool(SearchLogs::class, ['event' => 'not.an.event'])
        ->assertHasErrors();

    AdminServer::tool(SearchLogs::class, ['value' => 'ord_1'])
        ->assertHasErrors([LogFilterInput::VALUE_NEEDS_KEY]);
});

it('caps limit at two hundred', function (): void {
    AdminServer::tool(SearchLogs::class, ['limit' => 201])
        ->assertHasErrors();
});

it('says so when the log store is unavailable', function (): void {
    $this->app->instance(LogStore::class, LogStore::open('off'));

    AdminServer::tool(SearchLogs::class, [])
        ->assertHasErrors([SearchLogs::STORE_UNAVAILABLE]);
});
