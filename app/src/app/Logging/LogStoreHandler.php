<?php

declare(strict_types=1);

namespace App\Logging;

use Monolog\Formatter\FormatterInterface;
use Monolog\Handler\Handler;
use Monolog\LogRecord;
use Throwable;

/**
 * The store's own handler: formats the record with the exact
 * `App\Logging\StoryFormatter` instance the stdout handler uses — so the
 * two outputs cannot drift — and turns the resulting line into a
 * `LogStore` row. `App\Logging\LogStoreTap` places this handler after
 * every existing handler on the channel, so stdout has already written
 * the line by the time `handle()` runs here. Nothing this handler does
 * ever propagates: a formatting or store failure is swallowed, per the
 * store's third invariant that its failure is never the app's.
 */
final class LogStoreHandler extends Handler
{
    public function __construct(
        private readonly LogStore $store,
        private readonly FormatterInterface $formatter,
    ) {}

    public function isHandling(LogRecord $record): bool
    {
        return true;
    }

    public function handle(LogRecord $record): bool
    {
        try {
            $formatted = $this->formatter->format($record);
            // The formatter appends exactly one trailing newline, per the
            // §2 line shape; a mirrored `raw` line does not carry it, the
            // same as a line that arrived already split on '\n'.
            $line = rtrim(is_string($formatted) ? $formatted : '', "\n");
            $this->store->append(LogLine::parse($line));
        } catch (Throwable) {
            // The store's own failures never reach the logger it mirrors.
        }

        return false;
    }
}
