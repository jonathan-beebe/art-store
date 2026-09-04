<?php

declare(strict_types=1);

namespace App\Domain\Seller;

/**
 * One of the seller's own support threads, as the support hub lists it.
 */
final readonly class SupportThreadRow
{
    public function __construct(
        public string $conversationId,
        public string $title,
        public ?string $preview,
        public bool $isResolved,
    ) {}
}
