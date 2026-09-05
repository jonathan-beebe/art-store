<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use App\Logging\LogStore;
use App\Mcp\AdminServer;
use Illuminate\Testing\Fluent\AssertableJson;
use Tests\LogViewerFixtures as Fixtures;

it('answers one request\'s lines in the order they happened', function (): void {
    $this->app->instance(LogStore::class, Fixtures::store([
        Fixtures::line(['ts' => '2026-08-24T12:00:01.000Z', 'msg' => 'second', 'request_id' => 'req_a']),
        Fixtures::line(['ts' => '2026-08-24T12:00:00.000Z', 'msg' => 'first', 'request_id' => 'req_a']),
        Fixtures::line(['ts' => '2026-08-24T12:00:00.500Z', 'msg' => 'elsewhere', 'request_id' => 'req_b']),
    ]));

    AdminServer::tool(ShowRequest::class, ['request_id' => 'req_a'])
        ->assertOk()
        ->assertStructuredContent(fn (AssertableJson $json) => $json
            ->where('request_id', 'req_a')
            ->where('total', 2)
            ->where('capped', false)
            ->has('lines', 2)
            ->where('lines.0.msg', 'first')
            ->where('lines.1.msg', 'second')
            ->etc());
});

it('requires a well-formed request id', function (): void {
    $this->app->instance(LogStore::class, Fixtures::store([]));

    AdminServer::tool(ShowRequest::class, [])->assertHasErrors();
    AdminServer::tool(ShowRequest::class, ['request_id' => 'has a space'])->assertHasErrors();
});

it('says so when the log store is unavailable', function (): void {
    $this->app->instance(LogStore::class, LogStore::open('off'));

    AdminServer::tool(ShowRequest::class, ['request_id' => 'req_a'])
        ->assertHasErrors([ShowRequest::STORE_UNAVAILABLE]);
});
