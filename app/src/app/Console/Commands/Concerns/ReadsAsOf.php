<?php

declare(strict_types=1);

namespace App\Console\Commands\Concerns;

use DateMalformedStringException;
use DateTimeImmutable;

/**
 * A console run has no controller to freeze `now()` for it (see
 * docs/architecture.md "The clock"), so every command that accepts
 * `--as-of` parses it here instead, the same way.
 */
trait ReadsAsOf
{
    private function asOf(string $purpose): ?DateTimeImmutable
    {
        $rawAsOf = $this->option('as-of');
        $asOfInput = is_string($rawAsOf) && $rawAsOf !== '' ? $rawAsOf : null;

        try {
            return $asOfInput === null ? now()->toDateTimeImmutable() : new DateTimeImmutable($asOfInput);
        } catch (DateMalformedStringException) {
            $this->error("\"{$asOfInput}\" is not a date {$purpose}.");

            return null;
        }
    }
}
