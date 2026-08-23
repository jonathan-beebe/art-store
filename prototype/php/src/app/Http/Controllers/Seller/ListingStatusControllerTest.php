<?php

declare(strict_types=1);

namespace App\Http\Controllers\Seller;

use App\Domain\Listings\ListingStatus;
use Tests\CommerceTestCase;

final class ListingStatusControllerTest extends CommerceTestCase
{
    public function test_it_sends_a_signed_out_visitor_to_the_sign_in_page(): void
    {
        $listing = $this->listing($this->seller());

        $this->post("/seller/listings/{$listing->id}/status", ['status' => 'for_sale'])
            ->assertRedirect(route('auth.seller.login'));
    }

    public function test_it_puts_a_draft_up_for_sale(): void
    {
        $seller = $this->seller();
        $listing = $this->listing($seller, ['status' => ListingStatus::Draft]);

        $response = $this->actingAs($seller, 'seller')
            ->post("/seller/listings/{$listing->id}/status", ['status' => 'for_sale']);

        $response->assertRedirect(route('seller.listings.index'));
        $this->assertSame(ListingStatus::ForSale, $listing->fresh()->status);
    }

    public function test_it_archives_a_listing_that_is_for_sale(): void
    {
        $seller = $this->seller();
        $listing = $this->listing($seller, ['status' => ListingStatus::ForSale]);

        $this->actingAs($seller, 'seller')
            ->post("/seller/listings/{$listing->id}/status", ['status' => 'archived']);

        $this->assertSame(ListingStatus::Archived, $listing->fresh()->status);
    }

    public function test_it_rejects_a_transition_the_lifecycle_does_not_allow(): void
    {
        $seller = $this->seller();
        $listing = $this->listing($seller, ['status' => ListingStatus::Draft]);

        $response = $this->actingAs($seller, 'seller')
            ->post("/seller/listings/{$listing->id}/status", ['status' => 'sold']);

        $response->assertSessionHasErrors('status');
        $this->assertSame(ListingStatus::Draft, $listing->fresh()->status);
    }

    public function test_it_rejects_a_status_that_is_not_a_listing_status(): void
    {
        $seller = $this->seller();
        $listing = $this->listing($seller, ['status' => ListingStatus::Draft]);

        $response = $this->actingAs($seller, 'seller')
            ->post("/seller/listings/{$listing->id}/status", ['status' => 'on_fire']);

        $response->assertSessionHasErrors('status');
    }

    public function test_it_offers_no_transition_out_of_archived(): void
    {
        $seller = $this->seller();
        $listing = $this->listing($seller, ['status' => ListingStatus::Archived]);

        $response = $this->actingAs($seller, 'seller')
            ->post("/seller/listings/{$listing->id}/status", ['status' => 'for_sale']);

        $response->assertSessionHasErrors('status');
        $this->assertSame(ListingStatus::Archived, $listing->fresh()->status);
    }

    public function test_the_index_renders_only_the_transitions_the_status_allows(): void
    {
        $seller = $this->seller();
        $this->listing($seller, ['status' => ListingStatus::Draft, 'title' => 'A draft']);

        $response = $this->actingAs($seller, 'seller')->get('/seller/listings');

        $response->assertSee('value="for_sale"', escape: false);
        $response->assertSee('value="archived"', escape: false);
        $response->assertDontSee('value="sold"', escape: false);
    }

    public function test_it_refuses_to_change_another_sellers_listing(): void
    {
        $listing = $this->listing($this->seller('Other Studio'), ['status' => ListingStatus::Draft]);

        $response = $this->actingAs($this->seller(), 'seller')
            ->post("/seller/listings/{$listing->id}/status", ['status' => 'for_sale']);

        $response->assertNotFound();
        $this->assertSame(ListingStatus::Draft, $listing->fresh()->status);
    }
}
