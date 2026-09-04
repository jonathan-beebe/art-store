<?php

declare(strict_types=1);

namespace App\Seller;

/**
 * One row of the orders list pane, ready to render: the buyer a seller
 * recognises the sale by, the scan line, the badge and the day, and the one
 * line that says why this row wants attention.
 */
final readonly class OrderRow
{
    public function __construct(
        public string $id,
        public string $href,
        public bool $selected,
        public string $buyer,
        public string $itemLabel,
        public string $subtotal,
        public string $statusLabel,
        public string $tint,
        public string $placed,
        public ?string $note = null,
    ) {}
}
