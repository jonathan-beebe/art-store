<?php

declare(strict_types=1);

namespace App\Http\Controllers\Shop;

use App\Domain\Listings\ListingEventType;
use App\Models\Customer;
use App\Models\Favorite;
use App\Models\ListingEvent;
use Tests\StorefrontTestCase;

final class FavoriteControllerTest extends StorefrontTestCase
{
    public function test_it_favorites_a_listing_and_records_the_event(): void
    {
        $visitor = $this->visitor();
        $listing = $this->listing($this->seller(), ['slug' => 'harbour-at-dawn']);

        $response = $this->post('/art/harbour-at-dawn/favorite');

        $response->assertRedirect();
        $favorite = Favorite::sole();
        $this->assertSame($listing->id, $favorite->listing_id);
        $this->assertSame($visitor->id, $favorite->customer_id);
        $this->assertSame(ListingEventType::Favorite, ListingEvent::sole()->type);
    }

    public function test_favoriting_twice_removes_the_favorite(): void
    {
        $this->visitor();
        $this->listing($this->seller(), ['slug' => 'harbour-at-dawn']);

        $this->post('/art/harbour-at-dawn/favorite');
        $this->post('/art/harbour-at-dawn/favorite');

        $this->assertSame(0, Favorite::count());
        $this->assertSame(
            [ListingEventType::Favorite, ListingEventType::Unfavorite],
            ListingEvent::orderBy('id')->get()->pluck('type')->all(),
        );
    }

    public function test_favorites_saved_before_signing_in_survive_the_merge(): void
    {
        $this->visitor();
        Customer::factory()->create(['email' => 'shopper@example.com']);
        $this->listing($this->seller(), ['slug' => 'harbour-at-dawn', 'title' => 'Harbour at Dawn']);
        $this->post('/art/harbour-at-dawn/favorite');

        $this->post('/login', ['email' => 'shopper@example.com']);
        $this->get(session('debug_magic_link'));

        $this->get('/favorites')->assertSee('Harbour at Dawn');
        $this->assertSame(1, Favorite::count());
    }

    public function test_it_lists_the_visitor_favorites(): void
    {
        $this->visitor();
        $seller = $this->seller();
        $this->listing($seller, ['slug' => 'harbour-at-dawn', 'title' => 'Harbour at Dawn']);
        $this->listing($seller, ['slug' => 'winter-elm', 'title' => 'Winter Elm']);
        $this->post('/art/harbour-at-dawn/favorite');

        $response = $this->get('/favorites');

        $response->assertOk();
        $response->assertSee('Harbour at Dawn');
        $response->assertDontSee('Winter Elm');
    }
}
