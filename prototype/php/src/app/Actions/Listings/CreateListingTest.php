<?php

namespace App\Actions\Listings;

use App\Domain\Listings\ListingDraft;
use App\Domain\Listings\ListingStatus;
use App\Domain\Money\Money;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\CommerceTestCase;

final class CreateListingTest extends CommerceTestCase
{
    public function test_it_writes_the_drafted_fields(): void
    {
        $listing = ($this->createListing())($this->seller(), $this->draft());

        $this->assertSame('Harbour at Dusk', $listing->title);
        $this->assertSame('Oil on linen.', $listing->description);
        $this->assertSame('oil', $listing->medium);
        $this->assertSame('12 x 16 in', $listing->dimensions);
        $this->assertSame(24500, $listing->price_cents);
        $this->assertSame(2, $listing->quantity);
    }

    public function test_it_starts_a_listing_as_a_draft(): void
    {
        $listing = ($this->createListing())($this->seller(), $this->draft());

        $this->assertSame(ListingStatus::Draft, $listing->status);
    }

    public function test_it_belongs_to_the_seller_that_created_it(): void
    {
        $seller = $this->seller();

        $listing = ($this->createListing())($seller, $this->draft());

        $this->assertSame($seller->id, $listing->seller_id);
    }

    public function test_it_slugs_the_title(): void
    {
        $listing = ($this->createListing())($this->seller(), $this->draft());

        $this->assertSame('harbour-at-dusk', $listing->slug);
    }

    public function test_it_numbers_a_slug_another_listing_already_holds(): void
    {
        $createListing = $this->createListing();
        $createListing($this->seller(), $this->draft());

        $second = $createListing($this->seller(), $this->draft());

        $this->assertSame('harbour-at-dusk-2', $second->slug);
    }

    public function test_it_leaves_a_listing_without_an_upload_imageless(): void
    {
        $listing = ($this->createListing())($this->seller(), $this->draft());

        $this->assertNull($listing->image_path);
    }

    public function test_it_stores_an_uploaded_image_on_the_public_disk(): void
    {
        Storage::fake('public');

        $listing = ($this->createListing())($this->seller(), $this->draft(), UploadedFile::fake()->image('harbour.jpg'));

        $this->assertNotNull($listing->image_path);
        Storage::disk('public')->assertExists($listing->image_path);
        $this->assertStringStartsWith('listings/', $listing->image_path);
    }

    private function createListing(): CreateListing
    {
        return app(CreateListing::class);
    }

    private function draft(): ListingDraft
    {
        return new ListingDraft(
            'Harbour at Dusk',
            'Oil on linen.',
            'oil',
            '12 x 16 in',
            Money::fromCents(24500),
            2,
        );
    }
}
