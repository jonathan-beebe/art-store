<?php

declare(strict_types=1);

namespace App\Seller;

/**
 * A seller's buyer threads in two numbers: how many stand open, and how
 * many of those hold a message the seller has not read.
 */
final readonly class ConversationCounts
{
    public function __construct(
        public int $open,
        public int $unread,
    ) {}
}
