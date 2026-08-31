<?php

declare(strict_types=1);

namespace App\Logging;

use DateTimeZone;
use Monolog\Formatter\FormatterInterface;
use Monolog\Level;
use Monolog\LogRecord;
use Throwable;

/**
 * Writes one log record as the single-line JSON object of
 * docs/alignment.md §2.1, the same object in every environment.
 *
 * The fields the story fills — event, phase, the request marks, the unit of
 * work, the data, the duration — arrive in the record's context, which is
 * where `Log::withContext()` and every `Story` call put them. A line the
 * framework wrote on its own carries none of them, so it is still spelled as
 * a payload: `app.log` names the source, and the level decides whether it
 * reads as something in progress or something that broke.
 */
final readonly class StoryFormatter implements FormatterInterface
{
    /**
     * ISO-8601 UTC with milliseconds. `v` is the three-digit millisecond.
     */
    private const string TIMESTAMP = 'Y-m-d\TH:i:s.v\Z';

    private const string FRAMEWORK_EVENT = 'app.log';

    private const int JSON_FLAGS = JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE;

    /** docs/alignment.md §2.4: derived from the line's level and phase in
     * one place, never picked at a call site. */
    private const string WARN_PREFIX = '⚠️ ';

    private const string FAILED_PREFIX = '❌ ';

    /**
     * @param  bool  $tracesStacks  development shows the stack behind a
     *                              failure; a deployed environment does not
     */
    public function __construct(private bool $tracesStacks = false) {}

    public function format(LogRecord $record): string
    {
        return (json_encode($this->payload($record), self::JSON_FLAGS) ?: '{}')."\n";
    }

    /**
     * @param  array<array-key, LogRecord>  $records
     */
    public function formatBatch(array $records): string
    {
        return implode('', array_map($this->format(...), $records));
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(LogRecord $record): array
    {
        $context = $record->context;
        $level = $this->level($record->level);
        $phase = $this->text($context, 'phase') ?? $this->phase($level)->value;

        return array_filter([
            'ts' => $record->datetime->setTimezone(new DateTimeZone('UTC'))->format(self::TIMESTAMP),
            'level' => $level->value,
            'event' => $this->text($context, 'event') ?? self::FRAMEWORK_EVENT,
            'phase' => $phase,
            'msg' => $this->prefixed($record->message, $level, $phase),
            'request_id' => $this->text($context, 'request_id'),
            'session_id' => $this->text($context, 'session_id'),
            'actor_type' => $this->text($context, 'actor_type'),
            'actor_id' => $this->text($context, 'actor_id'),
            'txn_id' => $this->text($context, 'txn_id'),
            'data' => $this->data($context),
            'error' => $this->error($context),
            'duration_ms' => $this->duration($context),
        ], fn (mixed $value): bool => $value !== null);
    }

    /**
     * The `msg` prefix that makes a warning or a failure stand out to a
     * person reading plain stdout (docs/alignment.md §2.4): every `warn`
     * line gets ⚠️, every `failed` line gets ❌, everything else is bare.
     */
    private function prefixed(string $message, StoryLevel $level, string $phase): string
    {
        return match (true) {
            $phase === StoryPhase::Failed->value => self::FAILED_PREFIX.$message,
            $level === StoryLevel::Warn => self::WARN_PREFIX.$message,
            default => $message,
        };
    }

    private function level(Level $level): StoryLevel
    {
        return match (true) {
            $level->value <= Level::Debug->value => StoryLevel::Debug,
            $level->value < Level::Warning->value => StoryLevel::Info,
            $level->value < Level::Error->value => StoryLevel::Warn,
            default => StoryLevel::Error,
        };
    }

    /**
     * A framework line names no phase of its own. An error is the end of
     * something; anything quieter is a step along the way.
     */
    private function phase(StoryLevel $level): StoryPhase
    {
        return $level === StoryLevel::Error ? StoryPhase::Failed : StoryPhase::Doing;
    }

    /**
     * @param  array<mixed>  $context
     */
    private function text(array $context, string $key): ?string
    {
        $value = $context[$key] ?? null;

        return is_string($value) && $value !== '' ? $value : null;
    }

    /**
     * @param  array<mixed>  $context
     * @return array<mixed>|null
     */
    private function data(array $context): ?array
    {
        $data = $context['data'] ?? null;

        return is_array($data) && $data !== [] ? $data : null;
    }

    /**
     * @param  array<mixed>  $context
     */
    private function duration(array $context): ?int
    {
        $duration = $context['duration_ms'] ?? null;

        return is_int($duration) ? $duration : null;
    }

    /**
     * The framework logs an exception under `exception` and so does the
     * story, so one reading serves both.
     *
     * @param  array<mixed>  $context
     * @return array<string, string>|null
     */
    private function error(array $context): ?array
    {
        $error = $context['exception'] ?? null;

        if (! $error instanceof Throwable) {
            return null;
        }

        return array_filter([
            'type' => $error::class,
            'message' => $error->getMessage(),
            'stack' => $this->tracesStacks ? $error->getTraceAsString() : null,
        ], fn (?string $value): bool => $value !== null);
    }
}
