<?php

declare(strict_types=1);

namespace App\Http\Controllers\Seller;

use App\Domain\RateLimiting\RateLimitValue;
use App\Models\DescriptionSection;
use Illuminate\Support\Facades\Config;

it('moves a section up', function (): void {
    $seller = $this->seller();
    $listing = $this->listing($seller);
    $first = DescriptionSection::factory()->create(['listing_id' => $listing->id, 'position' => 0]);
    $second = DescriptionSection::factory()->create(['listing_id' => $listing->id, 'position' => 1]);

    $response = $this->actingAs($seller, 'seller')->post("/seller/listings/{$listing->id}/description-sections/{$second->id}/reorder", [
        'direction' => 'up',
    ]);

    $response->assertRedirect(route('seller.listings.description-sections.index', $listing));
    expect($second->fresh()?->position)->toBe(0)
        ->and($first->fresh()?->position)->toBe(1);
});

it('answers not found reordering a section from a different listing', function (): void {
    $seller = $this->seller();
    $listing = $this->listing($seller);
    $otherListing = $this->listing($seller);
    $section = DescriptionSection::factory()->create(['listing_id' => $otherListing->id]);

    $response = $this->actingAs($seller, 'seller')->post("/seller/listings/{$listing->id}/description-sections/{$section->id}/reorder", [
        'direction' => 'up',
    ]);

    $response->assertNotFound();
});

it('refuses reordering another sellers section', function (): void {
    $listing = $this->listing($this->seller('Other Studio'));
    $section = DescriptionSection::factory()->create(['listing_id' => $listing->id]);

    $response = $this->actingAs($this->seller(), 'seller')->post("/seller/listings/{$listing->id}/description-sections/{$section->id}/reorder", [
        'direction' => 'up',
    ]);

    $response->assertNotFound();
});

it('trips the listing-write limit reordering a section', function (): void {
    Config::set('rate_limits.listing_write', RateLimitValue::parse('1/1h', 'RATE_LIMIT_LISTING_WRITE'));
    $seller = $this->seller();
    $listing = $this->listing($seller);
    $first = DescriptionSection::factory()->create(['listing_id' => $listing->id, 'position' => 0]);
    $second = DescriptionSection::factory()->create(['listing_id' => $listing->id, 'position' => 1]);
    $this->actingAs($seller, 'seller')->post("/seller/listings/{$listing->id}/description-sections/{$second->id}/reorder", ['direction' => 'up']);

    $response = $this->actingAs($seller, 'seller')->post("/seller/listings/{$listing->id}/description-sections/{$first->id}/reorder", ['direction' => 'up']);

    $response->assertStatus(429);
});
