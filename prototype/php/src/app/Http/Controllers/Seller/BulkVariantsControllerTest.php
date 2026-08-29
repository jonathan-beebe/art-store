<?php

declare(strict_types=1);

namespace App\Http\Controllers\Seller;

use App\Domain\RateLimiting\RateLimitValue;
use App\Models\OptionAxis;
use App\Models\OptionValue;
use App\Models\Variant;
use App\Models\VariantOption;
use Illuminate\Support\Facades\Config;

it('disables every variant selecting the chosen option value', function (): void {
    $seller = $this->seller();
    $listing = $this->listing($seller);
    $axis = OptionAxis::factory()->create(['listing_id' => $listing->id]);
    $value = OptionValue::factory()->create(['axis_id' => $axis->id]);
    $variant = Variant::factory()->create(['listing_id' => $listing->id, 'enabled' => true]);
    VariantOption::factory()->create(['variant_id' => $variant->id, 'axis_id' => $axis->id, 'option_value_id' => $value->id]);

    $response = $this->actingAs($seller, 'seller')->post("/seller/listings/{$listing->id}/variants/bulk", [
        'option_value_id' => $value->id,
        'enabled' => '0',
    ]);

    $response->assertRedirect(route('seller.listings.variants.index', $listing));
    expect($variant->fresh()?->enabled)->toBeFalse();
});

it('refuses an option value belonging to another listing', function (): void {
    $seller = $this->seller();
    $listing = $this->listing($seller);
    $otherListing = $this->listing($seller);
    $otherAxis = OptionAxis::factory()->create(['listing_id' => $otherListing->id]);
    $otherValue = OptionValue::factory()->create(['axis_id' => $otherAxis->id]);

    $response = $this->actingAs($seller, 'seller')->post("/seller/listings/{$listing->id}/variants/bulk", [
        'option_value_id' => $otherValue->id,
        'enabled' => '1',
    ]);

    $response->assertSessionHasErrors('option_value_id');
});

it('refuses a bulk toggle on another sellers listing', function (): void {
    $listing = $this->listing($this->seller('Other Studio'));

    $response = $this->actingAs($this->seller(), 'seller')->post("/seller/listings/{$listing->id}/variants/bulk", [
        'option_value_id' => 'ovl_whatever',
        'enabled' => '1',
    ]);

    $response->assertNotFound();
});

it('trips the listing-write limit on a bulk toggle', function (): void {
    Config::set('rate_limits.listing_write', RateLimitValue::parse('1/1h', 'RATE_LIMIT_LISTING_WRITE'));
    $seller = $this->seller();
    $listing = $this->listing($seller);
    $axis = OptionAxis::factory()->create(['listing_id' => $listing->id]);
    $value = OptionValue::factory()->create(['axis_id' => $axis->id]);
    $this->actingAs($seller, 'seller')->post("/seller/listings/{$listing->id}/variants/bulk", ['option_value_id' => $value->id, 'enabled' => '1']);

    $response = $this->actingAs($seller, 'seller')->post("/seller/listings/{$listing->id}/variants/bulk", ['option_value_id' => $value->id, 'enabled' => '0']);

    $response->assertStatus(429);
});
