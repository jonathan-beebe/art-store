<?php

declare(strict_types=1);

namespace App\Logging;

use DateTimeImmutable;
use Monolog\Formatter\FormatterInterface;
use Monolog\Level;
use Monolog\LogRecord;
use RuntimeException;
use Tests\LogStoreFixtures as Fixtures;

/**
 * @param  array<string, mixed>  $context
 */
function handlerRecord(array $context = []): LogRecord
{
    return new LogRecord(
        datetime: new DateTimeImmutable('2026-08-23T18:00:00.001Z'),
        channel: 'testing',
        level: Level::Info,
        message: 'placed the order',
        context: $context,
    );
}

it('formats the record and appends the parsed line to the store', function (): void {
    $store = LogStore::open(Fixtures::tempFile());
    $handler = new LogStoreHandler($store, new StoryFormatter);

    $bubbled = $handler->handle(handlerRecord(['event' => 'order.place', 'phase' => 'did']));
    $store->flush();

    expect($bubbled)->toBeFalse()
        ->and(Fixtures::rowCount(Fixtures::connectionOrFail($store)))->toBe(1);
});

it('writes exactly the line the shared formatter produces', function (): void {
    $store = LogStore::open(Fixtures::tempFile());
    $formatter = new StoryFormatter;
    $handler = new LogStoreHandler($store, $formatter);
    $record = handlerRecord(['event' => 'order.place', 'phase' => 'did']);

    $handler->handle($record);
    $store->flush();

    $connection = Fixtures::connectionOrFail($store);
    $stored = (string) Fixtures::scalar($connection, 'SELECT raw FROM log_lines');

    expect($stored)->toBe(rtrim($formatter->format($record), "\n"));
});

it('always reports itself as handling, mirroring whatever the channel already let through', function (): void {
    $handler = new LogStoreHandler(LogStore::open('off'), new StoryFormatter);

    expect($handler->isHandling(handlerRecord()))->toBeTrue();
});

it('swallows a store failure rather than letting it escape handle()', function (): void {
    $store = LogStore::open(Fixtures::tempFile());
    Fixtures::connectionOrFail($store)->exec('DROP TABLE log_lines');
    $handler = new LogStoreHandler($store, new StoryFormatter);

    $bubbled = $handler->handle(handlerRecord(['event' => 'order.place', 'phase' => 'did']));
    $store->flush();

    expect($bubbled)->toBeFalse();
});

it('swallows a formatter failure the same way', function (): void {
    $store = LogStore::open(Fixtures::tempFile());
    $formatter = new class implements FormatterInterface
    {
        public function format(LogRecord $record): string
        {
            throw new RuntimeException('the formatter broke');
        }

        /**
         * @param  array<LogRecord>  $records
         */
        public function formatBatch(array $records): string
        {
            return '';
        }
    };
    $handler = new LogStoreHandler($store, $formatter);

    $bubbled = $handler->handle(handlerRecord());

    expect($bubbled)->toBeFalse()
        ->and(Fixtures::rowCount(Fixtures::connectionOrFail($store)))->toBe(0);
});
