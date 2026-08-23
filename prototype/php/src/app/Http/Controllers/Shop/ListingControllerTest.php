<?php

declare(strict_types=1);

namespace App\Http\Controllers\Shop;

use App\Domain\Listings\ListingEventType;
use App\Domain\Listings\ListingStatus;
use App\Models\ListingEvent;
use Tests\StorefrontTestCase;

final class ListingControllerTest extends StorefrontTestCase
{
    public function test_it_shows_the_listing_in_full(): void
    {
        $listing = $this->listing($this->seller('Blue Kiln Studio'), [
            'title' => 'Harbour at Dawn',
            'slug' => 'harbour-at-dawn',
            'description' => 'A quiet morning over the water.',
            'medium' => 'oil',
            'dimensions' => '12 x 16 in',
            'price_cents' => 24500,
        ]);

        $response = $this->get('/art/'.$listing->slug);

        $response->assertOk();
        $response->assertSee('Harbour at Dawn');
        $response->assertSee('Blue Kiln Studio');
        $response->assertSee('A quiet morning over the water.');
        $response->assertSee('oil');
        $response->assertSee('12 x 16 in');
        $response->assertSee('$245.00');
    }

    public function test_it_records_a_view_event_for_the_visitor(): void
    {
        $visitor = $this->visitor();
        $listing = $this->listing($this->seller(), ['slug' => 'harbour-at-dawn']);

        $this->get('/art/harbour-at-dawn');

        $event = ListingEvent::sole();
        $this->assertSame(ListingEventType::View, $event->type);
        $this->assertSame($listing->id, $event->listing_id);
        $this->assertSame($visitor->id, $event->customer_id);
    }

    public function test_a_sold_listing_says_so_and_offers_no_cart_button(): void
    {
        $this->listing($this->seller(), [
            'slug' => 'sold-vase',
            'status' => ListingStatus::Sold,
            'quantity' => 0,
        ]);

        $response = $this->get('/art/sold-vase');

        $response->assertOk();
        $response->assertSee('Sold');
        $response->assertDontSee('Add to cart');
    }

    public function test_a_draft_listing_is_not_on_the_storefront(): void
    {
        $this->listing($this->seller(), ['slug' => 'unfinished', 'status' => ListingStatus::Draft]);

        $this->get('/art/unfinished')->assertNotFound();
    }
}
