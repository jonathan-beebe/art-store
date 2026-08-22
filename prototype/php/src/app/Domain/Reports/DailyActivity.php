<?php

namespace App\Domain\Reports;

use DateTimeImmutable;

final readonly class DailyActivity
{
    public function __construct(
        public string $date,
        public int $views,
        public int $favorites,
        public int $cartAdds,
    ) {}

    public function total(): int
    {
        return $this->views + $this->favorites + $this->cartAdds;
    }

    public function label(): string
    {
        return DateTimeImmutable::createFromFormat('!Y-m-d', $this->date)->format('M j');
    }
}
