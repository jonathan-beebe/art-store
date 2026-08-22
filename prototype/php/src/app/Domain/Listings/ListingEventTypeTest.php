<?php

namespace App\Domain\Listings;

use PHPUnit\Framework\TestCase;

final class ListingEventTypeTest extends TestCase
{
    public function test_it_names_the_storefront_interactions_the_seller_reports_on(): void
    {
        $this->assertSame(
            ['view', 'favorite', 'unfavorite', 'cart_add'],
            array_column(ListingEventType::cases(), 'value'),
        );
    }
}
