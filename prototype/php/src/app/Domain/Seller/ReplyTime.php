<?php

declare(strict_types=1);

namespace App\Domain\Seller;

use DateTimeImmutable;

/**
 * How long the desk took to answer, in the round words a seller reads on
 * the support hub ("41 minutes", "2 hours") rather than a duration a
 * machine would parse.
 */
final readonly class ReplyTime
{
    private function __construct(public string $text) {}

    public static function between(DateTimeImmutable $askedAt, DateTimeImmutable $answeredAt): self
    {
        $minutes = intdiv(max(0, $answeredAt->getTimestamp() - $askedAt->getTimestamp()), 60);

        if ($minutes < 1) {
            return new self('under a minute');
        }

        if ($minutes < 60) {
            return new self(self::plural($minutes, 'minute'));
        }

        return new self(self::plural(intdiv($minutes, 60), 'hour'));
    }

    private static function plural(int $count, string $unit): string
    {
        return $count === 1 ? "1 {$unit}" : "{$count} {$unit}s";
    }
}
