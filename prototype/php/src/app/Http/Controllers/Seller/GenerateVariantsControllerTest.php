<?php

declare(strict_types=1);

namespace App\Http\Controllers\Seller;

use App\Domain\RateLimiting\RateLimitValue;
use App\Models\OptionAxis;
use App\Models\OptionValue;
use App\Models\Variant;
use Illuminate\Support\Facades\Config;

it('generates every combination of the listing’s axes', function (): void {
    $seller = $this->seller();
    $listing = $this->listing($seller);
    $axis = OptionAxis::factory()->create(['listing_id' => $listing->id]);
    OptionValue::factory()->count(2)->create(['axis_id' => $axis->id]);

    $response = $this->actingAs($seller, 'seller')->post("/seller/listings/{$listing->id}/variants/generate");

    $response->assertRedirect(route('seller.listings.variants.index', $listing));
    expect(Variant::where('listing_id', $listing->id)->count())->toBe(2);
});

it('refuses generating combinations for another sellers listing', function (): void {
    $listing = $this->listing($this->seller('Other Studio'));

    $response = $this->actingAs($this->seller(), 'seller')->post("/seller/listings/{$listing->id}/variants/generate");

    $response->assertNotFound();
});

it('trips the listing-write limit generating combinations', function (): void {
    Config::set('rate_limits.listing_write', RateLimitValue::parse('1/1h', 'RATE_LIMIT_LISTING_WRITE'));
    $seller = $this->seller();
    $listing = $this->listing($seller);
    $axis = OptionAxis::factory()->create(['listing_id' => $listing->id]);
    OptionValue::factory()->create(['axis_id' => $axis->id]);
    $this->actingAs($seller, 'seller')->post("/seller/listings/{$listing->id}/variants/generate");

    $response = $this->actingAs($seller, 'seller')->post("/seller/listings/{$listing->id}/variants/generate");

    $response->assertStatus(429);
});
