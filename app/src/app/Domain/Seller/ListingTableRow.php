<?php

declare(strict_types=1);

namespace App\Domain\Seller;

use App\Domain\Listings\ListingStockLabel;
use App\Domain\Money\Money;
use DateTimeImmutable;

/**
 * One row of the seller listings table and grid: a listing joined to its
 * ranged analytics counts and its all-time sales, built by
 * {@see \App\Seller\ListingTable}.
 */
final readonly class ListingTableRow
{
    public function __construct(
        public string $id,
        public string $title,
        public string $imageUrl,
        public ?string $medium,
        public ?string $dimensions,
        public string $statusLabel,
        public string $statusTint,
        public int $priceCents,
        public ?int $quantity,
        public int $views,
        public int $favorites,
        public int $cartAdds,
        public int $sold,
        public int $revenueCents,
        public DateTimeImmutable $updatedAt,
    ) {}

    public function price(): Money
    {
        return Money::fromCents($this->priceCents);
    }

    public function revenue(): Money
    {
        return Money::fromCents($this->revenueCents);
    }

    public function stockLabel(): string
    {
        return ListingStockLabel::withInStock($this->quantity);
    }

    /** Sold divided by views, or null when the listing has no views to divide by. */
    public function conversion(): ?float
    {
        return $this->views > 0 ? $this->sold / $this->views : null;
    }

    /** "12.3%" for a listing with views, "—" for one with none. */
    public function conversionLabel(): string
    {
        $conversion = $this->conversion();

        return $conversion === null ? '—' : number_format($conversion * 100, 1).'%';
    }
}
