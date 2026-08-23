<?php

declare(strict_types=1);

namespace App\Http\Controllers\Seller;

use App\Domain\Listings\ListingStatus;

it('puts a draft up for sale', function (): void {
    $seller = $this->seller();
    $listing = $this->listing($seller, ['status' => ListingStatus::Draft]);

    $response = $this->actingAs($seller, 'seller')
        ->post("/seller/listings/{$listing->id}/status", ['status' => 'for_sale']);

    $response->assertRedirect(route('seller.listings.index'));
    expect($listing->refresh()->status)->toBe(ListingStatus::ForSale);
});

it('archives a listing that is for sale', function (): void {
    $seller = $this->seller();
    $listing = $this->listing($seller, ['status' => ListingStatus::ForSale]);

    $this->actingAs($seller, 'seller')
        ->post("/seller/listings/{$listing->id}/status", ['status' => 'archived']);

    expect($listing->refresh()->status)->toBe(ListingStatus::Archived);
});

it('renders only the transitions the status allows', function (): void {
    $seller = $this->seller();
    $this->listing($seller, ['status' => ListingStatus::Draft, 'title' => 'A draft']);

    $response = $this->actingAs($seller, 'seller')->get('/seller/listings');

    $response->assertSee('value="for_sale"', escape: false);
    $response->assertSee('value="archived"', escape: false);
    $response->assertDontSee('value="sold"', escape: false);
});

it('refuses to change another sellers listing', function (): void {
    $listing = $this->listing($this->seller('Other Studio'), ['status' => ListingStatus::Draft]);

    $response = $this->actingAs($this->seller(), 'seller')
        ->post("/seller/listings/{$listing->id}/status", ['status' => 'for_sale']);

    $response->assertNotFound();
    expect($listing->refresh()->status)->toBe(ListingStatus::Draft);
});
