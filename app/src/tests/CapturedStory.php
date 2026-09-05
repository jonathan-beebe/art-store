<?php

declare(strict_types=1);

namespace Tests;

use App\Logging\StoryFormatter;
use Illuminate\Log\Logger as ApplicationLogger;
use Illuminate\Support\Facades\Log;
use Monolog\Handler\AbstractProcessingHandler;
use Monolog\Level;
use Monolog\Logger as Monolog;
use Monolog\LogRecord;
use PHPUnit\Framework\Assert;

/**
 * Holds the log a test's work wrote, as the deployed lines: every record goes
 * through the same formatter the stdout channel uses, so what a test reads is
 * the JSON a reader would.
 */
final class CapturedStory extends AbstractProcessingHandler
{
    /** @var list<string> */
    private array $written = [];

    /**
     * Puts this handler behind the `Log` facade for the rest of the test.
     */
    public static function capture(bool $tracesStacks = false): self
    {
        $handler = new self(Level::Debug);
        $handler->setFormatter(new StoryFormatter($tracesStacks));

        Log::swap(new ApplicationLogger(new Monolog('testing', [$handler])));

        return $handler;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function lines(): array
    {
        return array_map($this->decode(...), $this->written);
    }

    /**
     * What one field held on every line that carried it, in order.
     *
     * @return list<string>
     */
    public function values(string $field, ?string $event = null): array
    {
        $values = [];

        foreach ($event === null ? $this->lines() : $this->linesFor($event) as $line) {
            $value = $line[$field] ?? null;

            if (is_string($value)) {
                $values[] = $value;
            }
        }

        return $values;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function linesFor(string $event): array
    {
        return array_values(array_filter(
            $this->lines(),
            fn (array $line): bool => ($line['event'] ?? null) === $event,
        ));
    }

    /**
     * The one line for an event and phase, so a test can read its fields.
     *
     * @return array<string, mixed>
     */
    public function line(string $event, string $phase): array
    {
        $matches = array_values(array_filter(
            $this->linesFor($event),
            fn (array $line): bool => ($line['phase'] ?? null) === $phase,
        ));

        Assert::assertNotEmpty($matches, "No {$event} {$phase} line was written.");

        return $matches[0];
    }

    /**
     * Every line as `<event> <phase>`, in the order they were written, which
     * is how a test reads a story rather than a field.
     *
     * @return list<string>
     */
    public function outline(): array
    {
        return array_map(
            fn (array $line): string => "{$this->text($line, 'event')} {$this->text($line, 'phase')}",
            $this->lines(),
        );
    }

    /**
     * The whole capture as one string, for the tests that assert something
     * appears nowhere in it.
     */
    public function raw(): string
    {
        return implode('', $this->written);
    }

    protected function write(LogRecord $record): void
    {
        $formatted = $record->formatted;

        $this->written[] = is_string($formatted) ? $formatted : '';
    }

    /**
     * @return array<string, mixed>
     */
    private function decode(string $line): array
    {
        $decoded = json_decode($line, true);

        Assert::assertIsArray($decoded, "A log line was not a JSON object: {$line}");

        /** @var array<string, mixed> $decoded */
        return $decoded;
    }

    /**
     * @param  array<string, mixed>  $line
     */
    private function text(array $line, string $key): string
    {
        $value = $line[$key] ?? null;

        return is_string($value) ? $value : '';
    }
}
