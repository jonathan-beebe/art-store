<?php

declare(strict_types=1);

namespace App\Domain\Seller;

use App\Domain\Money\Money;
use DateTimeImmutable;

/**
 * One buyer of one seller: the numbers the customers table shows and the
 * thread rail repeats, built by {@see \App\Seller\SellerCustomers}. A row
 * exists only for a customer holding at least one live fulfillment with
 * the seller, so it always carries a first and a last order.
 */
final readonly class CustomerRow
{
    /** Two orders is the line between a buyer and a repeat buyer. */
    private const int REPEAT_ORDERS = 2;

    public function __construct(
        public string $customerId,
        public string $name,
        public ?string $email,
        public int $orders,
        public int $spentCents,
        public int $favorites,
        public int $conversations,
        public DateTimeImmutable $firstOrderAt,
        public DateTimeImmutable $lastOrderAt,
    ) {}

    public function spent(): Money
    {
        return Money::fromCents($this->spentCents);
    }

    public function isRepeatBuyer(): bool
    {
        return $this->orders >= self::REPEAT_ORDERS;
    }

    /** Whether the buyer's first order falls inside a window opening at `$from`. */
    public function isNewSince(DateTimeImmutable $from): bool
    {
        return $this->firstOrderAt >= $from;
    }

    /** The first letter of each of the first two words of the name. */
    public function initials(): string
    {
        $words = array_filter(preg_split('/\s+/', trim($this->name)) ?: []);

        $initials = array_map(
            fn (string $word): string => mb_strtoupper(mb_substr($word, 0, 1)),
            array_slice($words, 0, 2),
        );

        return implode('', $initials);
    }
}
