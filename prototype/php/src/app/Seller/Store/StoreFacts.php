<?php

declare(strict_types=1);

namespace App\Seller\Store;

use DateTimeImmutable;

/**
 * The line under a store's name: how many pieces are for sale and how long
 * the maker has been selling. Read once per render and handed to the same
 * component the seller previews and the buyer opens.
 */
final readonly class StoreFacts
{
    public function __construct(
        public int $pieceCount,
        public ?DateTimeImmutable $sellingSince,
    ) {}

    /** "8 pieces for sale · Selling since June 2026", with either half dropped when it has nothing to say. */
    public function sentence(): string
    {
        $parts = [$this->pieceCount === 1 ? '1 piece for sale' : $this->pieceCount.' pieces for sale'];

        if ($this->sellingSince instanceof DateTimeImmutable) {
            $parts[] = 'Selling since '.$this->sellingSince->format('F Y');
        }

        return implode(' · ', $parts);
    }
}
