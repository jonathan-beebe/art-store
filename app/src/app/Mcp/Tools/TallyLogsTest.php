<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use App\Logging\Admin\LogFilterInput;
use App\Logging\LogStore;
use App\Mcp\AdminServer;
use Tests\LogViewerFixtures as Fixtures;

it('counts the matching lines per level, ignoring level itself', function (): void {
    $this->app->instance(LogStore::class, Fixtures::store([
        Fixtures::line(['level' => 'info', 'request_id' => 'req_a']),
        Fixtures::line(['level' => 'warn', 'request_id' => 'req_a']),
        Fixtures::line(['level' => 'error', 'phase' => 'failed', 'request_id' => 'req_a']),
        Fixtures::line(['level' => 'error', 'phase' => 'failed', 'request_id' => 'req_b']),
    ]));

    AdminServer::tool(TallyLogs::class, ['request' => 'req_a', 'level' => 'warn'])
        ->assertOk()
        ->assertStructuredContent([
            'total' => 3,
            'levels' => ['debug' => 0, 'info' => 1, 'warn' => 1, 'error' => 1],
        ]);
});

it('refuses a value without a key', function (): void {
    $this->app->instance(LogStore::class, Fixtures::store([]));

    AdminServer::tool(TallyLogs::class, ['value' => 'x'])
        ->assertHasErrors([LogFilterInput::VALUE_NEEDS_KEY]);
});

it('says so when the log store is unavailable', function (): void {
    $this->app->instance(LogStore::class, LogStore::open('off'));

    AdminServer::tool(TallyLogs::class, [])
        ->assertHasErrors([SearchLogs::STORE_UNAVAILABLE]);
});
