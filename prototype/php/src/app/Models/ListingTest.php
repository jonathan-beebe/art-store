<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Listings\ListingStatus;
use Tests\CommerceTestCase;

final class ListingTest extends CommerceTestCase
{
    public function test_only_listings_for_sale_reach_the_storefront(): void
    {
        $seller = $this->seller();
        $forSale = $this->listing($seller);
        $this->listing($seller, ['status' => ListingStatus::Draft]);
        $this->listing($seller, ['status' => ListingStatus::Sold, 'quantity' => 0]);

        $this->assertSame([$forSale->id], Listing::query()->forSale()->pluck('id')->all());
    }

    public function test_a_listing_reads_its_price_as_money(): void
    {
        $listing = $this->listing($this->seller(), ['price_cents' => 45000]);

        $this->assertSame('$450.00', $listing->price()->format());
    }

    public function test_a_listing_without_an_upload_renders_a_placeholder_image(): void
    {
        $listing = $this->listing($this->seller(), ['title' => 'Blue Heron', 'image_path' => null]);

        $this->assertStringStartsWith('data:image/svg+xml;base64,', $listing->imageUrl());
    }

    public function test_an_uploaded_image_is_served_from_the_public_disk(): void
    {
        $listing = $this->listing($this->seller(), ['image_path' => 'listings/heron.png']);

        $this->assertStringEndsWith('/storage/listings/heron.png', $listing->imageUrl());
    }
}
