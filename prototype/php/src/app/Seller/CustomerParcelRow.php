<?php

declare(strict_types=1);

namespace App\Seller;

/**
 * One row of the customer page's order list, ready to render: the scan
 * line and picture, the day and the money, and the status badge.
 */
final readonly class CustomerParcelRow
{
    public function __construct(
        public string $href,
        public string $imageUrl,
        public string $itemLabel,
        public string $placed,
        public string $subtotal,
        public string $statusLabel,
        public string $tint,
    ) {}
}
