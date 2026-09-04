<?php

declare(strict_types=1);

namespace App\Logging;

use DateTimeImmutable;
use DateTimeZone;

it('maps every §2.1 field to its column', function (): void {
    $line = LogLine::parse(json_encode([
        'ts' => '2026-08-23T18:00:00.019Z',
        'level' => 'info',
        'event' => 'order.place',
        'phase' => 'did',
        'msg' => 'placed the order',
        'request_id' => 'req_1',
        'session_id' => 'ses_01J00000000000000000000ABC',
        'actor_type' => 'customer',
        'actor_id' => 'cus_01J00000000000000000000ABC',
        'txn_id' => 'txn_01J00000000000000000000ABC',
        'duration_ms' => 15,
        'data' => ['order_id' => 'ord_01J00000000000000000000ABC', 'total_cents' => 12000],
    ], JSON_THROW_ON_ERROR));

    expect($line->ts)->toBe('2026-08-23T18:00:00.019Z')
        ->and($line->level)->toBe('info')
        ->and($line->event)->toBe('order.place')
        ->and($line->phase)->toBe('did')
        ->and($line->msg)->toBe('placed the order')
        ->and($line->requestId)->toBe('req_1')
        ->and($line->sessionId)->toBe('ses_01J00000000000000000000ABC')
        ->and($line->actorType)->toBe('customer')
        ->and($line->actorId)->toBe('cus_01J00000000000000000000ABC')
        ->and($line->txnId)->toBe('txn_01J00000000000000000000ABC')
        ->and($line->durationMs)->toBe(15)
        ->and($line->data)->toBe('{"order_id":"ord_01J00000000000000000000ABC","total_cents":12000}')
        ->and($line->error)->toBeNull();
});

it('carries the error object as re-serialized JSON text', function (): void {
    $line = LogLine::parse(json_encode([
        'ts' => '2026-08-23T18:00:00.019Z',
        'event' => 'order.pay',
        'phase' => 'failed',
        'error' => ['type' => 'RuntimeException', 'message' => 'the checkout broke'],
    ], JSON_THROW_ON_ERROR));

    expect($line->error)->toBe('{"type":"RuntimeException","message":"the checkout broke"}');
});

it('leaves a field the line does not carry null', function (): void {
    $line = LogLine::parse(json_encode(['ts' => '2026-08-23T18:00:00.019Z', 'event' => 'app.boot', 'phase' => 'did'], JSON_THROW_ON_ERROR));

    expect($line->level)->toBeNull()
        ->and($line->msg)->toBeNull()
        ->and($line->requestId)->toBeNull()
        ->and($line->sessionId)->toBeNull()
        ->and($line->actorType)->toBeNull()
        ->and($line->actorId)->toBeNull()
        ->and($line->txnId)->toBeNull()
        ->and($line->durationMs)->toBeNull()
        ->and($line->data)->toBeNull()
        ->and($line->error)->toBeNull();
});

it('stores a field present but JSON null as the text "null", distinct from an absent field', function (): void {
    $line = LogLine::parse(json_encode(['ts' => '2026-08-23T18:00:00.019Z', 'data' => null], JSON_THROW_ON_ERROR));

    expect($line->data)->toBe('null');
});

it('falls back to a receive-time ts when the line carries none', function (): void {
    // Compared as millisecond-precision strings, the same precision `ts`
    // itself is stored at — a microsecond-precision $before could sort
    // after a truncated-not-rounded $line->ts from the same instant.
    $before = (new DateTimeImmutable('now', new DateTimeZone('UTC')))->format('Y-m-d\TH:i:s.v\Z');

    $line = LogLine::parse(json_encode(['event' => 'app.boot'], JSON_THROW_ON_ERROR));

    $after = (new DateTimeImmutable('now', new DateTimeZone('UTC')))->format('Y-m-d\TH:i:s.v\Z');

    expect($line->ts)->toMatch('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}\.\d{3}Z$/')
        ->and($line->ts)->toBeGreaterThanOrEqual($before)
        ->and($line->ts)->toBeLessThanOrEqual($after);
});

it('stores a line that fails to parse as JSON as raw plus a receive-time ts, every other column null', function (string $malformed): void {
    $line = LogLine::parse($malformed);

    expect($line->raw)->toBe($malformed)
        ->and($line->level)->toBeNull()
        ->and($line->event)->toBeNull()
        ->and($line->phase)->toBeNull()
        ->and($line->msg)->toBeNull()
        ->and($line->requestId)->toBeNull()
        ->and($line->sessionId)->toBeNull()
        ->and($line->actorType)->toBeNull()
        ->and($line->actorId)->toBeNull()
        ->and($line->txnId)->toBeNull()
        ->and($line->durationMs)->toBeNull()
        ->and($line->data)->toBeNull()
        ->and($line->error)->toBeNull()
        ->and($line->ts)->toMatch('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}\.\d{3}Z$/');
})->with([
    'not JSON at all' => ['this is not json'],
    'a JSON array rather than an object' => ['["order.place", "did"]'],
    'a bare JSON scalar' => ['"just a string"'],
    'a JSON null' => ['null'],
]);

it('caps raw at 64 KiB, keeping the mirrored columns extracted from the full line', function (): void {
    $padding = str_repeat('x', 64 * 1024);
    $line = LogLine::parse(json_encode(['ts' => '2026-08-23T18:00:00.019Z', 'event' => 'order.place', 'msg' => $padding], JSON_THROW_ON_ERROR));

    expect(strlen($line->raw))->toBe(64 * 1024)
        ->and($line->event)->toBe('order.place')
        ->and($line->msg)->toBe($padding);
});

it('gives the columns in log_lines order', function (): void {
    $line = LogLine::parse(json_encode([
        'ts' => '2026-08-23T18:00:00.019Z',
        'level' => 'info',
        'event' => 'order.place',
        'phase' => 'did',
        'msg' => 'placed the order',
        'request_id' => 'req_1',
        'session_id' => 'ses_1',
        'actor_type' => 'customer',
        'actor_id' => 'cus_1',
        'txn_id' => 'txn_1',
        'duration_ms' => 15,
        'data' => ['a' => 1],
    ], JSON_THROW_ON_ERROR));

    expect($line->columns())->toBe([
        '2026-08-23T18:00:00.019Z', 'info', 'order.place', 'did', 'placed the order',
        'req_1', 'ses_1', 'customer', 'cus_1', 'txn_1', 15, '{"a":1}', null,
        json_encode(['ts' => '2026-08-23T18:00:00.019Z', 'level' => 'info', 'event' => 'order.place', 'phase' => 'did', 'msg' => 'placed the order', 'request_id' => 'req_1', 'session_id' => 'ses_1', 'actor_type' => 'customer', 'actor_id' => 'cus_1', 'txn_id' => 'txn_1', 'duration_ms' => 15, 'data' => ['a' => 1]], JSON_THROW_ON_ERROR),
    ]);
});
