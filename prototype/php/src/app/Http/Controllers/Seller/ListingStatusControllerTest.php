<?php

declare(strict_types=1);

namespace App\Http\Controllers\Seller;

use App\Domain\DomainRuleViolation;
use App\Domain\Listings\ListingStatus;
use App\Http\Requests\Seller\ChangeListingStatusRequest;
use App\Models\ListingRemoval;
use App\Models\OptionAxis;
use App\Models\Variant;
use Tests\CapturedStory;

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

it('offers no button to put a removed listing back on the storefront', function (): void {
    $seller = $this->seller();
    $listing = $this->listing($seller, ['status' => ListingStatus::Sold, 'title' => 'A removed piece']);
    ListingRemoval::factory()->create(['listing_id' => $listing->id]);

    $response = $this->actingAs($seller, 'seller')->get('/seller/listings');

    $response->assertSee('A removed piece');
    $response->assertDontSee('value="for_sale"', escape: false);
});

it('refuses to publish a listing with configurator issues, sending the seller to the edit screen', function (): void {
    $seller = $this->seller();
    $listing = $this->listing($seller, ['status' => ListingStatus::Draft]);
    $axis = OptionAxis::factory()->create(['listing_id' => $listing->id]);
    $variant = Variant::factory()->create(['listing_id' => $listing->id, 'price_override_cents' => -100]);

    $response = $this->actingAs($seller, 'seller')
        ->post("/seller/listings/{$listing->id}/status", ['status' => 'for_sale']);

    $response->assertRedirect(route('seller.listings.edit', $listing));
    expect($listing->refresh()->status)->toBe(ListingStatus::Draft);

    $editResponse = $this->actingAs($seller, 'seller')->get(route('seller.listings.edit', $listing));
    $editResponse->assertSee('Not ready to publish');
    $editResponse->assertSee(route('seller.listings.variants.index', $listing).'#'.$variant->id, escape: false);
});

it('refuses to change another sellers listing', function (): void {
    $listing = $this->listing($this->seller('Other Studio'), ['status' => ListingStatus::Draft]);

    $response = $this->actingAs($this->seller(), 'seller')
        ->post("/seller/listings/{$listing->id}/status", ['status' => 'for_sale']);

    $response->assertNotFound();
    expect($listing->refresh()->status)->toBe(ListingStatus::Draft);
});

it('refuses a transition the status stopped allowing after the form was validated', function (): void {
    $seller = $this->seller();
    $listing = $this->listing($seller, ['status' => ListingStatus::Archived]);

    // The form request admits only the transitions the status held when it
    // ran. Reaching the controller with a status that has moved since is the
    // race, and the core is what refuses it.
    $request = ChangeListingStatusRequest::create(
        "/seller/listings/{$listing->id}/status",
        'POST',
        ['status' => ListingStatus::ForSale->value],
    );

    $log = CapturedStory::capture();

    expect(fn () => (new ListingStatusController)($request, $listing))
        ->toThrow(DomainRuleViolation::class);

    $line = $log->line('listing.transition', 'refused');

    expect($line['level'])->toBe('info')
        ->and($line['data'])->toBe([
            'listing_id' => $listing->id,
            'status_from' => 'archived',
            'status_to' => 'for_sale',
        ]);
});
