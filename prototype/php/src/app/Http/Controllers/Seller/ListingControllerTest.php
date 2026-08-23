<?php

declare(strict_types=1);

namespace App\Http\Controllers\Seller;

use App\Actions\Listings\RecordListingEvent;
use App\Domain\Listings\ListingEventType;
use App\Domain\Listings\ListingStatus;
use App\Models\Listing;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\CommerceTestCase;

final class ListingControllerTest extends CommerceTestCase
{
    public function test_it_sends_a_signed_out_visitor_to_the_sign_in_page(): void
    {
        $this->get('/seller/listings')->assertRedirect(route('auth.seller.login'));
    }

    public function test_it_lists_the_sellers_listings(): void
    {
        $seller = $this->seller();
        $this->listing($seller, ['title' => 'Harbour at Dusk']);

        $response = $this->actingAs($seller, 'seller')->get('/seller/listings');

        $response->assertOk();
        $response->assertSee('Harbour at Dusk');
    }

    public function test_it_keeps_another_sellers_listings_off_the_table(): void
    {
        $this->listing($this->seller('Other Studio'), ['title' => 'Not Mine']);

        $response = $this->actingAs($this->seller(), 'seller')->get('/seller/listings');

        $response->assertDontSee('Not Mine');
    }

    public function test_it_shows_the_event_counts_for_each_listing(): void
    {
        $seller = $this->seller();
        $listing = $this->listing($seller, ['title' => 'Harbour at Dusk']);
        $recordListingEvent = app(RecordListingEvent::class);
        $recordListingEvent($listing, null, ListingEventType::View, $this->moment('2026-08-20 09:00:00'));
        $recordListingEvent($listing, null, ListingEventType::View, $this->moment('2026-08-20 10:00:00'));
        $recordListingEvent($listing, null, ListingEventType::CartAdd, $this->moment('2026-08-20 11:00:00'));

        $response = $this->actingAs($seller, 'seller')->get('/seller/listings');

        $response->assertViewHas('listings', function ($listings): bool {
            return $listings->first()->views_count === 2
                && $listings->first()->favorites_count === 0
                && $listings->first()->cart_adds_count === 1;
        });
    }

    public function test_it_shows_a_placeholder_thumbnail_for_a_listing_without_an_image(): void
    {
        $seller = $this->seller();
        $listing = $this->listing($seller, ['image_path' => null]);

        $response = $this->actingAs($seller, 'seller')->get('/seller/listings');

        $response->assertSee($listing->imageUrl(), escape: false);
    }

    public function test_it_renders_the_create_form(): void
    {
        $response = $this->actingAs($this->seller(), 'seller')->get('/seller/listings/create');

        $response->assertOk();
        $response->assertSee('New listing');
        $response->assertSee('for="price"', escape: false);
    }

    public function test_it_creates_a_listing_from_the_form(): void
    {
        $seller = $this->seller();

        $response = $this->actingAs($seller, 'seller')->post('/seller/listings', $this->form());

        $response->assertRedirect(route('seller.listings.index'));
        $listing = Listing::where('seller_id', $seller->id)->sole();
        $this->assertSame('Harbour at Dusk', $listing->title);
        $this->assertSame(24900, $listing->price_cents);
        $this->assertSame(ListingStatus::Draft, $listing->status);
    }

    public function test_it_stores_an_uploaded_image_on_the_public_disk(): void
    {
        Storage::fake('public');
        $seller = $this->seller();

        $this->actingAs($seller, 'seller')->post('/seller/listings', $this->form([
            'image' => UploadedFile::fake()->image('harbour.jpg'),
        ]));

        $listing = Listing::where('seller_id', $seller->id)->sole();
        Storage::disk('public')->assertExists($listing->image_path);
    }

    public function test_it_rejects_a_listing_without_a_title(): void
    {
        $response = $this->actingAs($this->seller(), 'seller')
            ->post('/seller/listings', $this->form(['title' => '']));

        $response->assertSessionHasErrors('title');
        $this->assertSame(0, Listing::count());
    }

    public function test_it_rejects_a_price_that_is_not_an_amount_in_dollars(): void
    {
        $response = $this->actingAs($this->seller(), 'seller')
            ->post('/seller/listings', $this->form(['price' => 'a lot']));

        $response->assertSessionHasErrors('price');
    }

    public function test_it_rejects_a_price_carrying_fractions_of_a_cent(): void
    {
        $response = $this->actingAs($this->seller(), 'seller')
            ->post('/seller/listings', $this->form(['price' => '249.999']));

        $response->assertSessionHasErrors('price');
    }

    public function test_it_rejects_a_negative_quantity(): void
    {
        $response = $this->actingAs($this->seller(), 'seller')
            ->post('/seller/listings', $this->form(['quantity' => -1]));

        $response->assertSessionHasErrors('quantity');
    }

    public function test_it_rejects_an_upload_that_is_not_an_image(): void
    {
        Storage::fake('public');

        $response = $this->actingAs($this->seller(), 'seller')->post('/seller/listings', $this->form([
            'image' => UploadedFile::fake()->create('notes.txt', 4, 'text/plain'),
        ]));

        $response->assertSessionHasErrors('image');
    }

    public function test_it_rejects_an_upload_that_only_claims_to_be_an_image(): void
    {
        Storage::fake('public');

        $response = $this->actingAs($this->seller(), 'seller')->post('/seller/listings', $this->form([
            'image' => UploadedFile::fake()->create('harbour.jpg', 12, 'image/jpeg'),
        ]));

        $response->assertSessionHasErrors('image');
        $this->assertSame(0, Listing::count());
    }

    public function test_it_renders_the_edit_form_with_the_price_in_dollars(): void
    {
        $seller = $this->seller();
        $listing = $this->listing($seller, ['title' => 'Harbour at Dusk', 'price_cents' => 24900]);

        $response = $this->actingAs($seller, 'seller')->get("/seller/listings/{$listing->id}/edit");

        $response->assertOk();
        $response->assertSee('value="249.00"', escape: false);
    }

    public function test_it_updates_a_listing_from_the_form(): void
    {
        $seller = $this->seller();
        $listing = $this->listing($seller, ['title' => 'Old title']);

        $response = $this->actingAs($seller, 'seller')->post("/seller/listings/{$listing->id}", $this->form());

        $response->assertRedirect(route('seller.listings.index'));
        $this->assertSame('Harbour at Dusk', $listing->fresh()->title);
        $this->assertSame(24900, $listing->fresh()->price_cents);
    }

    public function test_it_rejects_an_update_without_a_title(): void
    {
        $seller = $this->seller();
        $listing = $this->listing($seller, ['title' => 'Old title']);

        $response = $this->actingAs($seller, 'seller')
            ->post("/seller/listings/{$listing->id}", $this->form(['title' => '']));

        $response->assertSessionHasErrors('title');
        $this->assertSame('Old title', $listing->fresh()->title);
    }

    public function test_it_hides_another_sellers_listing_from_the_edit_form(): void
    {
        $listing = $this->listing($this->seller('Other Studio'));

        $response = $this->actingAs($this->seller(), 'seller')->get("/seller/listings/{$listing->id}/edit");

        $response->assertNotFound();
    }

    public function test_it_refuses_to_update_another_sellers_listing(): void
    {
        $listing = $this->listing($this->seller('Other Studio'), ['title' => 'Not Mine']);

        $response = $this->actingAs($this->seller(), 'seller')->post("/seller/listings/{$listing->id}", $this->form());

        $response->assertNotFound();
        $this->assertSame('Not Mine', $listing->fresh()->title);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function form(array $overrides = []): array
    {
        return $overrides + [
            'title' => 'Harbour at Dusk',
            'description' => 'Oil on linen.',
            'medium' => 'oil',
            'dimensions' => '12 x 16 in',
            'price' => '249.00',
            'quantity' => 1,
        ];
    }
}
