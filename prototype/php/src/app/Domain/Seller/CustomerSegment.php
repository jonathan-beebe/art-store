<?php

declare(strict_types=1);

namespace App\Domain\Seller;

use DateTimeImmutable;

/**
 * Which buyers the customers table lists. The range decides what "new"
 * means, so the segment reads the window's start it is handed.
 */
enum CustomerSegment: string
{
    case All = 'all';
    case Repeat = 'repeat';
    case New = 'new';

    public static function default(): self
    {
        return self::All;
    }

    public function label(): string
    {
        return match ($this) {
            self::All => 'All',
            self::Repeat => 'Repeat buyers',
            self::New => 'New this period',
        };
    }

    public function keeps(CustomerRow $row, DateTimeImmutable $rangeStart): bool
    {
        return match ($this) {
            self::All => true,
            self::Repeat => $row->isRepeatBuyer(),
            self::New => $row->isNewSince($rangeStart),
        };
    }

    /**
     * @param  list<CustomerRow>  $rows
     * @return list<CustomerRow>
     */
    public function apply(array $rows, DateTimeImmutable $rangeStart): array
    {
        return array_values(array_filter(
            $rows,
            fn (CustomerRow $row): bool => $this->keeps($row, $rangeStart),
        ));
    }
}
