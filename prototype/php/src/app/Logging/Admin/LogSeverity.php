<?php

declare(strict_types=1);

namespace App\Logging\Admin;

/**
 * docs/logging.md § "Severity tint": a row tints yellow on a `warn` line, red
 * on a `failed` line (`error` level) — red always winning. A request is a
 * conversation, so its `group=1` row and the story header tint from the
 * worst line the request contains.
 */
enum LogSeverity
{
    case None;
    case Warn;
    case Error;

    public static function ofLevel(?string $level): self
    {
        return match ($level) {
            'error' => self::Error,
            'warn' => self::Warn,
            default => self::None,
        };
    }

    /**
     * @param  iterable<LogRow>  $lines
     */
    public static function worstOf(iterable $lines): self
    {
        $worst = self::None;

        foreach ($lines as $line) {
            $severity = self::ofLevel($line->level);

            if ($severity === self::Error) {
                return self::Error;
            }

            if ($severity === self::Warn) {
                $worst = self::Warn;
            }
        }

        return $worst;
    }

    /** The scanning aid: a founder skimming the list or the grouped view
     * sees trouble without opening anything. */
    public function rowClasses(): string
    {
        return match ($this) {
            self::Error => 'bg-red-50 dark:bg-red-950/30',
            self::Warn => 'bg-amber-50 dark:bg-amber-950/20',
            self::None => '',
        };
    }
}
