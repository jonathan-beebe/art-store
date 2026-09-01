<?php

declare(strict_types=1);

namespace App\Domain\Listings;

it('reads a bare count, or made to order for a null quantity', function (): void {
    expect(ListingStockLabel::bare(4))->toBe('4')
        ->and(ListingStockLabel::bare(0))->toBe('0')
        ->and(ListingStockLabel::bare(null))->toBe('Made to order');
});

it('reads a count with "in stock", or made to order for a null quantity', function (): void {
    expect(ListingStockLabel::withInStock(4))->toBe('4 in stock')
        ->and(ListingStockLabel::withInStock(0))->toBe('0 in stock')
        ->and(ListingStockLabel::withInStock(null))->toBe('Made to order');
});
