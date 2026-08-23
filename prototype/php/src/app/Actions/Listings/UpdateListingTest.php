<?php

declare(strict_types=1);

namespace App\Actions\Listings;

use App\Domain\Listings\ListingDraft;
use App\Domain\Listings\ListingStatus;
use App\Domain\Money\Money;
use App\Models\Listing;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\CommerceTestCase;

final class UpdateListingTest extends CommerceTestCase
{
    public function test_it_writes_the_drafted_fields(): void
    {
        $listing = $this->listing($this->seller(), ['title' => 'Old title', 'price_cents' => 100]);

        ($this->updateListing())($listing, $this->draft());

        $this->assertSame('Harbour at Dawn', $listing->fresh()->title);
        $this->assertSame(9900, $listing->fresh()->price_cents);
    }

    public function test_it_keeps_the_slug_a_renamed_listing_was_shared_under(): void
    {
        $listing = $this->listing($this->seller(), ['title' => 'Old title', 'slug' => 'old-title']);

        ($this->updateListing())($listing, $this->draft());

        $this->assertSame('old-title', $listing->fresh()->slug);
    }

    public function test_it_keeps_the_status_the_listing_already_had(): void
    {
        $listing = $this->listing($this->seller(), ['status' => ListingStatus::ForSale]);

        ($this->updateListing())($listing, $this->draft());

        $this->assertSame(ListingStatus::ForSale, $listing->fresh()->status);
    }

    public function test_it_keeps_the_image_when_the_form_uploads_none(): void
    {
        $listing = $this->listing($this->seller(), ['image_path' => 'listings/kept.jpg']);

        ($this->updateListing())($listing, $this->draft());

        $this->assertSame('listings/kept.jpg', $listing->fresh()->image_path);
    }

    public function test_it_replaces_the_image_and_deletes_the_file_it_replaced(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('listings/old.jpg', 'old');
        $listing = $this->listing($this->seller(), ['image_path' => 'listings/old.jpg']);

        ($this->updateListing())($listing, $this->draft(), UploadedFile::fake()->image('new.jpg'));

        $this->assertNotSame('listings/old.jpg', $listing->fresh()->image_path);
        Storage::disk('public')->assertMissing('listings/old.jpg');
        Storage::disk('public')->assertExists($listing->fresh()->image_path);
    }

    public function test_it_leaves_other_listings_alone(): void
    {
        $seller = $this->seller();
        $listing = $this->listing($seller, ['title' => 'Mine']);
        $other = $this->listing($seller, ['title' => 'Untouched']);

        ($this->updateListing())($listing, $this->draft());

        $this->assertSame('Untouched', Listing::find($other->id)->title);
    }

    private function updateListing(): UpdateListing
    {
        return app(UpdateListing::class);
    }

    private function draft(): ListingDraft
    {
        return new ListingDraft(
            'Harbour at Dawn',
            'Oil on linen.',
            'oil',
            '12 x 16 in',
            Money::fromCents(9900),
            1,
        );
    }
}
