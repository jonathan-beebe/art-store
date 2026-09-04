<?php

declare(strict_types=1);

namespace App\Domain\Seller;

/**
 * Where each focus group's header link goes: the four seller tools that
 * clear the four kinds of work the dashboard surfaces.
 */
final readonly class AttentionLinks
{
    public function __construct(
        public string $orders,
        public string $messages,
        public string $earnings,
        public string $listings,
    ) {}
}
