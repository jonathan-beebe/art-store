<?php

declare(strict_types=1);

namespace App\Logging;

use Illuminate\Log\Logger;
use Monolog\Formatter\FormatterInterface;
use Monolog\Handler\FormattableHandlerInterface;
use Monolog\Handler\HandlerInterface;
use Monolog\Logger as Monolog;

/**
 * Registered as the `stdout` channel's `tap` in config/logging.php. Laravel
 * invokes a tap with the channel's `Illuminate\Log\Logger` once its
 * handlers are built (`Illuminate\Log\LogManager::get()`), so this runs
 * once per process, the first time something logs.
 *
 * It splices `App\Logging\LogStoreHandler` onto the underlying Monolog
 * logger's handler stack — AFTER every handler already there. Monolog runs
 * the front-of-stack handler first and `pushHandler()` prepends, so
 * rebuilding the stack with `setHandlers([...existing, $store])` is what
 * keeps the invariant that stdout writes a line before the store ever sees
 * it, rather than a runtime guard enforcing the order.
 */
final readonly class LogStoreTap
{
    public function __construct(private LogStore $store) {}

    public function __invoke(Logger $logger): void
    {
        $monolog = $logger->getLogger();

        if (! $monolog instanceof Monolog) {
            return;
        }

        $handlers = $monolog->getHandlers();

        $monolog->setHandlers([...$handlers, new LogStoreHandler($this->store, $this->formatter($handlers))]);
    }

    /**
     * The same formatter instance the first existing handler (stdout's
     * `StreamHandler`) writes with, so the store's line and the stdout line
     * cannot drift apart. Falls back to a fresh `StoryFormatter` for a
     * channel with no formattable handler of its own — defensive only; the
     * `stdout` channel this tap is configured on always has one.
     *
     * @param  list<HandlerInterface>  $handlers
     */
    private function formatter(array $handlers): FormatterInterface
    {
        $first = $handlers[0] ?? null;

        return $first instanceof FormattableHandlerInterface ? $first->getFormatter() : new StoryFormatter;
    }
}
