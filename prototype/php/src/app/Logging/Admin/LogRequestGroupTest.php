<?php

declare(strict_types=1);

namespace App\Logging\Admin;

it('carries the request summary and lines it was built with', function (): void {
    $line = LogRow::fromDatabase(['id' => 1, 'ts' => 't', 'request_id' => 'req_1'] + array_fill_keys(
        ['level', 'event', 'phase', 'msg', 'session_id', 'actor_type', 'actor_id', 'txn_id', 'duration_ms', 'data', 'error'],
        null,
    ));

    $group = new LogRequestGroup(
        key: 'req_1',
        kind: 'request',
        lineCount: 1,
        lastTs: 't',
        method: 'GET',
        path: '/checkout',
        status: 200,
        durationMs: 12,
        level: 'info',
        msg: 'GET /checkout 200',
        lines: [$line],
    );

    expect($group->key)->toBe('req_1')
        ->and($group->kind)->toBe('request')
        ->and($group->lineCount)->toBe(1)
        ->and($group->method)->toBe('GET')
        ->and($group->path)->toBe('/checkout')
        ->and($group->status)->toBe(200)
        ->and($group->durationMs)->toBe(12)
        ->and($group->level)->toBe('info')
        ->and($group->msg)->toBe('GET /checkout 200')
        ->and($group->lines)->toBe([$line]);
});
