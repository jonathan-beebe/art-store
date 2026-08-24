<?php

declare(strict_types=1);

namespace App\Logging;

use DateTimeImmutable;
use DateTimeZone;
use Monolog\Level;
use Monolog\LogRecord;
use RuntimeException;

/**
 * @param  array<string, mixed>  $context
 */
function record(array $context = [], Level $level = Level::Info, string $message = 'placing an order from the cart'): LogRecord
{
    return new LogRecord(
        datetime: new DateTimeImmutable('2026-08-23T18:00:00.001234+02:00'),
        channel: 'testing',
        level: $level,
        message: $message,
        context: $context,
    );
}

/**
 * @return array<string, mixed>
 */
function payload(LogRecord $record, bool $tracesStacks = false): array
{
    $line = (new StoryFormatter($tracesStacks))->format($record);

    expect($line)->toEndWith("\n")
        ->and(substr_count($line, "\n"))->toBe(1);

    /** @var array<string, mixed> $decoded */
    $decoded = json_decode(trim($line), true, flags: JSON_THROW_ON_ERROR);

    return $decoded;
}

it('writes the timestamp as ISO-8601 UTC with milliseconds', function (): void {
    expect(payload(record()))->toHaveKey('ts', '2026-08-23T16:00:00.001Z');
});

it('carries every field the payload names, in the order the contract lists them', function (): void {
    $line = payload(record([
        'event' => 'order.place',
        'phase' => 'did',
        'request_id' => 'req_01J00000000000000000000ABC',
        'session_id' => 'ses_01J00000000000000000000ABC',
        'actor_type' => 'customer',
        'actor_id' => 'cus_01J00000000000000000000ABC',
        'txn_id' => 'txn_01J00000000000000000000ABC',
        'data' => ['order_id' => 'ord_01J00000000000000000000ABC', 'total_cents' => 12000],
        'duration_ms' => 15,
    ]), tracesStacks: false);

    expect(array_keys($line))->toBe([
        'ts', 'level', 'event', 'phase', 'msg',
        'request_id', 'session_id', 'actor_type', 'actor_id', 'txn_id',
        'data', 'duration_ms',
    ])
        ->and($line['msg'])->toBe('placing an order from the cart')
        ->and($line['data'])->toBe(['order_id' => 'ord_01J00000000000000000000ABC', 'total_cents' => 12000]);
});

it('leaves out a field the record carries nothing for', function (string $field): void {
    expect(payload(record(['event' => 'order.place', 'phase' => 'will'])))->not->toHaveKey($field);
})->with(['request_id', 'session_id', 'actor_type', 'actor_id', 'txn_id', 'data', 'error', 'duration_ms']);

it('names the level the payload spells rather than the one Monolog does', function (Level $monolog, string $level): void {
    expect(payload(record(['event' => 'app.boot', 'phase' => 'did'], $monolog)))->toHaveKey('level', $level);
})->with([
    'debug' => [Level::Debug, 'debug'],
    'info' => [Level::Info, 'info'],
    'notice reads as info' => [Level::Notice, 'info'],
    'warning is spelled warn' => [Level::Warning, 'warn'],
    'error' => [Level::Error, 'error'],
    'anything past error is still error' => [Level::Emergency, 'error'],
]);

it('gives a line the framework wrote on its own an event and a phase', function (Level $level, string $phase): void {
    expect(payload(record([], $level, 'something the framework said')))
        ->toHaveKey('event', 'app.log')
        ->toHaveKey('phase', $phase);
})->with([
    'a quiet line is a step along the way' => [Level::Info, 'doing'],
    'an error ends something' => [Level::Error, 'failed'],
]);

it('reads the exception the record carries as the error object', function (): void {
    $line = payload(record([
        'event' => 'http.request',
        'phase' => 'failed',
        'exception' => new RuntimeException('the checkout broke'),
    ], Level::Error));

    expect($line['error'])->toBe([
        'type' => RuntimeException::class,
        'message' => 'the checkout broke',
    ]);
});

it('shows the stack behind a failure only while tracing is on', function (): void {
    $record = record(['event' => 'http.request', 'phase' => 'failed', 'exception' => new RuntimeException('broke')], Level::Error);

    /** @var array<string, mixed> $traced */
    $traced = payload($record, tracesStacks: true)['error'];

    expect($traced)->toHaveKey('stack')
        ->and(payload($record, tracesStacks: false)['error'])->not->toHaveKey('stack');
});

it('ignores a context value that is not the shape the field holds', function (): void {
    $line = payload(record([
        'event' => 'order.place',
        'phase' => 'will',
        'request_id' => '',
        'txn_id' => 42,
        'data' => 'not an object',
        'exception' => 'not a throwable',
        'duration_ms' => '15',
    ]));

    expect($line)->not->toHaveKey('request_id')
        ->and($line)->not->toHaveKey('txn_id')
        ->and($line)->not->toHaveKey('data')
        ->and($line)->not->toHaveKey('error')
        ->and($line)->not->toHaveKey('duration_ms');
});

it('leaves out data that holds nothing', function (): void {
    expect(payload(record(['event' => 'order.place', 'phase' => 'will', 'data' => []])))->not->toHaveKey('data');
});

it('writes a batch as one line per record', function (): void {
    $lines = (new StoryFormatter)->formatBatch([
        record(['event' => 'app.boot', 'phase' => 'did']),
        record(['event' => 'app.shutdown', 'phase' => 'did']),
    ]);

    expect(substr_count($lines, "\n"))->toBe(2);
});

it('keeps a slash and a unicode character as they were written', function (): void {
    $line = payload(record(['event' => 'http.request', 'phase' => 'will', 'data' => ['path' => '/café/listings']]));

    /** @var array<string, mixed> $data */
    $data = $line['data'];

    expect($data['path'])->toBe('/café/listings');
});

it('formats the timestamp of a record already in UTC unchanged', function (): void {
    $record = new LogRecord(
        datetime: new DateTimeImmutable('2026-08-23T18:00:00.019000', new DateTimeZone('UTC')),
        channel: 'testing',
        level: Level::Info,
        message: 'placed the order',
        context: ['event' => 'order.place', 'phase' => 'did'],
    );

    expect(payload($record))->toHaveKey('ts', '2026-08-23T18:00:00.019Z');
});
