<?php

declare(strict_types=1);

namespace App\Seller;

use App\Domain\Money\Money;
use DateTimeImmutable;

/**
 * What one order's page says about the buyer behind it: who they are, and
 * what they have bought from this seller.
 */
final readonly class CustomerFacts
{
    public function __construct(
        public string $name,
        public ?string $email,
        public int $orders,
        public Money $spend,
        public ?DateTimeImmutable $since,
    ) {}

    /**
     * The card's third line: how many orders, how much, and since when.
     */
    public function line(): string
    {
        $orders = $this->orders === 1 ? '1 order' : "{$this->orders} orders";
        $line = $orders.' · '.$this->spend->format();

        return $this->since === null ? $line : $line.' · since '.$this->since->format('M j, Y');
    }
}
