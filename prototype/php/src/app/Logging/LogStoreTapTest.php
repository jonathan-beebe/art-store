<?php

declare(strict_types=1);

namespace App\Logging;

use Illuminate\Log\Logger as ApplicationLogger;
use Monolog\Handler\Handler;
use Monolog\Logger as Monolog;
use Monolog\LogRecord;
use Psr\Log\NullLogger;
use RuntimeException;
use Tests\LogStoreFixtures as Fixtures;
use Tests\SpyLogHandler;

it('appends the store handler after every handler already on the stack', function (): void {
    $existing = new SpyLogHandler('existing');
    $monolog = new Monolog('testing', [$existing]);
    $logger = new ApplicationLogger($monolog);

    (new LogStoreTap(LogStore::open('off')))($logger);

    $handlers = $monolog->getHandlers();
    expect($handlers)->toHaveCount(2)
        ->and($handlers[0])->toBe($existing)
        ->and($handlers[1])->toBeInstanceOf(LogStoreHandler::class);
});

it('runs the existing handler before the store handler, on every record', function (): void {
    $existing = new SpyLogHandler('existing');
    $monolog = new Monolog('testing', [$existing]);
    $logger = new ApplicationLogger($monolog);
    $store = LogStore::open(Fixtures::tempFile());

    (new LogStoreTap($store))($logger);
    $monolog->info('placing an order from the cart', ['event' => 'order.place', 'phase' => 'will']);
    $store->flush();

    expect($existing->order())->toBe(['existing'])
        ->and(Fixtures::rowCount(Fixtures::connectionOrFail($store)))->toBe(1);
});

it("hands the store handler the first existing handler's own formatter", function (): void {
    $existing = new SpyLogHandler('existing');
    $existing->setFormatter(new StoryFormatter(tracesStacks: true));
    $monolog = new Monolog('testing', [$existing]);
    $logger = new ApplicationLogger($monolog);
    $store = LogStore::open(Fixtures::tempFile());

    (new LogStoreTap($store))($logger);
    $monolog->error('the checkout broke', [
        'event' => 'order.pay',
        'phase' => 'failed',
        'exception' => new RuntimeException('card declined'),
    ]);
    $store->flush();

    $raw = (string) Fixtures::scalar(Fixtures::connectionOrFail($store), 'SELECT raw FROM log_lines');
    /** @var array{error?: array<string, mixed>} $decoded */
    $decoded = json_decode($raw, true, flags: JSON_THROW_ON_ERROR);

    // A fresh, default App\Logging\StoryFormatter never traces a stack;
    // this is only present if the store used the handler's own
    // tracesStacks: true instance rather than one of its own.
    expect($decoded['error'] ?? [])->toHaveKey('stack');
});

it('falls back to a fresh StoryFormatter when the first handler is not formattable', function (): void {
    $notFormattable = new class extends Handler
    {
        public function isHandling(LogRecord $record): bool
        {
            return true;
        }

        public function handle(LogRecord $record): bool
        {
            return false;
        }
    };
    $monolog = new Monolog('testing', [$notFormattable]);
    $logger = new ApplicationLogger($monolog);
    $store = LogStore::open(Fixtures::tempFile());

    (new LogStoreTap($store))($logger);
    $monolog->info('placing an order from the cart', ['event' => 'order.place', 'phase' => 'will']);
    $store->flush();

    $raw = (string) Fixtures::scalar(Fixtures::connectionOrFail($store), 'SELECT raw FROM log_lines');
    /** @var array<string, mixed> $decoded */
    $decoded = json_decode($raw, true, flags: JSON_THROW_ON_ERROR);

    expect($decoded)->toHaveKey('event', 'order.place');
});

it('does nothing for a logger not backed by a Monolog\Logger', function (): void {
    $logger = new ApplicationLogger(new NullLogger);

    // Nothing to assert on the handler stack — a PSR logger with no
    // Monolog handlers of its own has none to append after.
    (new LogStoreTap(LogStore::open('off')))($logger);
})->throwsNoExceptions();
