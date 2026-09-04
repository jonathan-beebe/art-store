<?php

declare(strict_types=1);

namespace App\Logging\Admin;

it('reads a full database row', function (): void {
    $row = LogRow::fromDatabase([
        'id' => '7',
        'ts' => '2026-08-24T12:00:00.000Z',
        'level' => 'info',
        'event' => 'order.place',
        'phase' => 'did',
        'msg' => 'placed the order',
        'request_id' => 'req_1',
        'session_id' => 'ses_1',
        'actor_type' => 'customer',
        'actor_id' => 'cus_1',
        'txn_id' => 'txn_1',
        'duration_ms' => '15',
        'data' => '{"order_id":"ord_1"}',
        'error' => null,
    ]);

    expect($row->id)->toBe(7)
        ->and($row->ts)->toBe('2026-08-24T12:00:00.000Z')
        ->and($row->level)->toBe('info')
        ->and($row->event)->toBe('order.place')
        ->and($row->phase)->toBe('did')
        ->and($row->msg)->toBe('placed the order')
        ->and($row->requestId)->toBe('req_1')
        ->and($row->sessionId)->toBe('ses_1')
        ->and($row->actorType)->toBe('customer')
        ->and($row->actorId)->toBe('cus_1')
        ->and($row->txnId)->toBe('txn_1')
        ->and($row->durationMs)->toBe(15)
        ->and($row->data)->toBe('{"order_id":"ord_1"}')
        ->and($row->error)->toBeNull();
});

it('reads a malformed-line row where every mirrored column but raw fields is null', function (): void {
    $row = LogRow::fromDatabase([
        'id' => 1,
        'ts' => '2026-08-24T12:00:00.000Z',
        'level' => null,
        'event' => null,
        'phase' => null,
        'msg' => null,
        'request_id' => null,
        'session_id' => null,
        'actor_type' => null,
        'actor_id' => null,
        'txn_id' => null,
        'duration_ms' => null,
        'data' => null,
        'error' => null,
    ]);

    expect($row->level)->toBeNull()->and($row->durationMs)->toBeNull();
});

it('groups by its own request id when it has one', function (): void {
    $row = LogRow::fromDatabase(['id' => 3, 'ts' => 't', 'request_id' => 'req_9'] + array_fill_keys(
        ['level', 'event', 'phase', 'msg', 'session_id', 'actor_type', 'actor_id', 'txn_id', 'duration_ms', 'data', 'error'],
        null,
    ));

    expect($row->groupKey())->toBe('req_9');
});

it('groups alone, keyed by its own id, when it has no request id', function (): void {
    $row = LogRow::fromDatabase(['id' => 3, 'ts' => 't', 'request_id' => null] + array_fill_keys(
        ['level', 'event', 'phase', 'msg', 'session_id', 'actor_type', 'actor_id', 'txn_id', 'duration_ms', 'data', 'error'],
        null,
    ));

    expect($row->groupKey())->toBe('line:3');
});
