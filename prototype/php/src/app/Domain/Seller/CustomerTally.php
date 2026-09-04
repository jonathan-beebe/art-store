<?php

declare(strict_types=1);

namespace App\Domain\Seller;

use App\Domain\Money\Money;

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

    public static function of(CustomerTallyFacts $facts, int $openConversations, int $unreadConversations): self
    {
        return new self(
            customers: $facts->customers,
            newThisPeriod: $facts->newThisPeriod,
            repeatBuyers: $facts->repeatBuyers,
            orders: $facts->orders,
            spentCents: $facts->spentCents,
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
