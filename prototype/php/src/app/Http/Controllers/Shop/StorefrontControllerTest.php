<?php

declare(strict_types=1);

namespace App\Http\Controllers\Shop;

use App\Domain\Listings\ListingStatus;
use Tests\StorefrontTestCase;

final class StorefrontControllerTest extends StorefrontTestCase
{
    public function test_it_shows_a_for_sale_listing_with_its_artist_and_price(): void
    {
        $seller = $this->seller('Blue Kiln Studio');
        $this->listing($seller, ['title' => 'Harbour at Dawn', 'price_cents' => 24500]);

        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee('Harbour at Dawn');
        $response->assertSee('Blue Kiln Studio');
        $response->assertSee('$245.00');
    }

    public function test_it_leaves_out_listings_that_are_not_for_sale(): void
    {
        $seller = $this->seller();
        $this->listing($seller, ['title' => 'Unfinished Sketch', 'status' => ListingStatus::Draft]);
        $this->listing($seller, ['title' => 'Sold Vase', 'status' => ListingStatus::Sold, 'quantity' => 0]);

        $response = $this->get('/');

        $response->assertDontSee('Unfinished Sketch');
        $response->assertDontSee('Sold Vase');
    }

    public function test_it_searches_titles_descriptions_and_media(): void
    {
        $seller = $this->seller();
        $this->listing($seller, ['title' => 'Harbour at Dawn', 'description' => 'A quiet morning.', 'medium' => 'oil']);
        $this->listing($seller, ['title' => 'Kiln Study', 'description' => 'Fired in a harbour town.', 'medium' => 'ceramic']);
        $this->listing($seller, ['title' => 'Field Notes', 'description' => 'Pencil on paper.', 'medium' => 'harbour-blue print']);
        $this->listing($seller, ['title' => 'Winter Elm', 'description' => 'Bare branches.', 'medium' => 'watercolour']);

        $response = $this->get('/?q=harbour');

        $response->assertSee('Harbour at Dawn');
        $response->assertSee('Kiln Study');
        $response->assertSee('Field Notes');
        $response->assertDontSee('Winter Elm');
    }

    public function test_it_narrows_to_one_medium(): void
    {
        $seller = $this->seller();
        $this->listing($seller, ['title' => 'Harbour at Dawn', 'medium' => 'oil']);
        $this->listing($seller, ['title' => 'Kiln Study', 'medium' => 'ceramic']);

        $response = $this->get('/?medium=ceramic');

        $response->assertSee('Kiln Study');
        $response->assertDontSee('Harbour at Dawn');
    }

    public function test_it_offers_the_media_of_listings_that_are_for_sale(): void
    {
        $seller = $this->seller();
        $this->listing($seller, ['medium' => 'ceramic']);
        $this->listing($seller, ['medium' => 'linocut', 'status' => ListingStatus::Draft]);

        $response = $this->get('/');

        $response->assertSee('<option value="ceramic"', escape: false);
        $response->assertDontSee('<option value="linocut"', escape: false);
    }

    public function test_it_paginates_at_twelve_listings(): void
    {
        $seller = $this->seller();
        for ($index = 1; $index <= 13; $index++) {
            $this->listing($seller, ['title' => sprintf('Study No %02d', $index), 'price_cents' => 1000 + $index]);
        }

        $first = $this->get('/');
        $second = $this->get('/?page=2');

        $first->assertSee('Study No 13');
        $first->assertDontSee('Study No 01');
        $second->assertSee('Study No 01');
    }

    public function test_it_shows_a_flashed_magic_link_in_the_debug_alert(): void
    {
        $response = $this->withSession(['debug_magic_link' => 'http://localhost:8000/auth/magic/abc123'])->get('/');

        $response->assertSee('http://localhost:8000/auth/magic/abc123', escape: false);
    }

    public function test_it_hides_the_debug_alert_without_a_flashed_magic_link(): void
    {
        $response = $this->get('/');

        $response->assertDontSee('Debug magic link');
    }

    public function test_it_links_the_built_stylesheet(): void
    {
        $response = $this->get('/');

        $response->assertSee('/build/assets/', escape: false);
    }
}
