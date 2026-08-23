<?php

declare(strict_types=1);

namespace App\Domain\Listings;

it('names the storefront interactions the seller reports on', function (): void {
    expect(array_column(ListingEventType::cases(), 'value'))
        ->toBe(['view', 'favorite', 'unfavorite', 'cart_add']);
});
