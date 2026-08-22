<?php

namespace App\Domain\Escrow;

use DateTimeImmutable;

final readonly class PayoutPeriod
{
    private const DAYS_IN_A_WEEK = 7;

    private function __construct(public DateTimeImmutable $start, public DateTimeImmutable $end) {}

    public static function endingBefore(DateTimeImmutable $asOf): self
    {
        $daysSinceMonday = (int) $asOf->format('N') - 1;
        $start = $asOf->setTime(0, 0)->modify('-'.($daysSinceMonday + self::DAYS_IN_A_WEEK).' days');

        return new self($start, $start->modify('+'.self::DAYS_IN_A_WEEK.' days')->modify('-1 second'));
    }

    public function contains(DateTimeImmutable $moment): bool
    {
        return $moment >= $this->start && $moment <= $this->end;
    }

    public function label(): string
    {
        return $this->start->format('Y-m-d').' to '.$this->end->format('Y-m-d');
    }
}
