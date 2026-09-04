<?php

declare(strict_types=1);

namespace App\Domain\Seller;

use App\Domain\Money\Money;
use DateTimeImmutable;

/**
 * The four figures above the customers table. They count every buyer the
 * seller has, whatever the table's segment narrows to, so switching
 * segments never moves them.
 */
final readonly class CustomerTally
{
    private function __construct(
        public int $customers,
        public int $newThisPeriod,
        public int $repeatBuyers,
        public int $orders,
        public int $spentCents,
        public int $openConversations,
        public int $unreadConversations,
    ) {}

    /**
     * @param  list<CustomerRow>  $rows  every buyer, unfiltered
     */
    public static function of(array $rows, DateTimeImmutable $rangeStart, int $openConversations, int $unreadConversations): self
    {
        return new self(
            customers: count($rows),
            newThisPeriod: count(array_filter($rows, fn (CustomerRow $row): bool => $row->isNewSince($rangeStart))),
            repeatBuyers: count(array_filter($rows, fn (CustomerRow $row): bool => $row->isRepeatBuyer())),
            orders: array_sum(array_map(fn (CustomerRow $row): int => $row->orders, $rows)),
            spentCents: array_sum(array_map(fn (CustomerRow $row): int => $row->spentCents, $rows)),
            openConversations: $openConversations,
            unreadConversations: $unreadConversations,
        );
    }

    /** The share of buyers who have ordered twice, rounded to whole percent. Null before there is a buyer to divide by. */
    public function repeatShare(): ?int
    {
        return $this->customers === 0 ? null : (int) round($this->repeatBuyers / $this->customers * 100);
    }

    /** What an order is worth on average. Null before there is an order to divide by. */
    public function averageOrder(): ?Money
    {
        return $this->orders === 0 ? null : Money::fromCents(intdiv($this->spentCents, $this->orders));
    }
}
