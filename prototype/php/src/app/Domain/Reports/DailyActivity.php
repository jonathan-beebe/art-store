<?php

declare(strict_types=1);

namespace App\Domain\Reports;

use DateTimeImmutable;

final readonly class DailyActivity
{
    private function __construct(
        public DateTimeImmutable $date,
        public int $views,
        public int $favorites,
        public int $cartAdds,
    ) {}

    public static function on(DateTimeImmutable $date, int $views, int $favorites, int $cartAdds): self
    {
        return new self($date, $views, $favorites, $cartAdds);
    }

    public function total(): int
    {
        return $this->views + $this->favorites + $this->cartAdds;
    }

    public function label(): string
    {
        return $this->date->format('M j');
    }
}
