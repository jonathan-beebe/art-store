<?php

declare(strict_types=1);

namespace App\Domain\Messaging;

use App\Domain\DomainRuleViolation;

/**
 * What a thread is called: typed by a seller or a customer opening a support
 * thread, or derived from the question itself for a listing question. A
 * fulfillment thread carries none — it is named by its order everywhere it
 * appears.
 */
final readonly class ThreadTitle
{
    public const int MAX_LENGTH = 120;

    private const int SUMMARY_LENGTH = 80;

    private function __construct(public string $value) {}

    public static function of(string $value): self
    {
        if (mb_strlen($value) > self::MAX_LENGTH) {
            throw new DomainRuleViolation('A thread title cannot be longer than '.self::MAX_LENGTH.' characters.');
        }

        return new self($value);
    }

    /**
     * A listing question is titled by the question itself: its first line,
     * cut to 80 characters with an ellipsis when it runs longer.
     */
    public static function fromBody(string $body): self
    {
        $firstLine = trim(strtok(trim($body), "\n") ?: '');

        $summary = mb_strlen($firstLine) > self::SUMMARY_LENGTH
            ? mb_substr($firstLine, 0, self::SUMMARY_LENGTH - 1).'…'
            : $firstLine;

        return self::of($summary);
    }
}
