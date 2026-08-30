<?php

declare(strict_types=1);

namespace Tests;

use Monolog\Formatter\FormatterInterface;
use Monolog\Formatter\LineFormatter;
use Monolog\Handler\FormattableHandlerInterface;
use Monolog\Handler\Handler;
use Monolog\Handler\HandlerInterface;
use Monolog\LogRecord;

/**
 * Stands in for the stdout `StreamHandler` in `App\Logging\LogStoreTapTest`:
 * records that it ran, by name, so the test can observe handler call order
 * without parsing a real stream.
 */
final class SpyLogHandler extends Handler implements FormattableHandlerInterface
{
    private FormatterInterface $formatter;

    /** @var list<string> */
    private array $order = [];

    public function __construct(private readonly string $name)
    {
        $this->formatter = new LineFormatter;
    }

    public function isHandling(LogRecord $record): bool
    {
        return true;
    }

    public function handle(LogRecord $record): bool
    {
        $this->order[] = $this->name;

        return false;
    }

    /**
     * @return list<string>
     */
    public function order(): array
    {
        return $this->order;
    }

    public function setFormatter(FormatterInterface $formatter): HandlerInterface
    {
        $this->formatter = $formatter;

        return $this;
    }

    public function getFormatter(): FormatterInterface
    {
        return $this->formatter;
    }
}
