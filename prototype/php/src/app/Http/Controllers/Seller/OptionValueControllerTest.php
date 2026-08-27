<?php

declare(strict_types=1);

namespace App\Http\Controllers\Seller;

use App\Domain\RateLimiting\RateLimitValue;
use App\Models\OptionAxis;
use App\Models\OptionValue;
use App\Models\Variant;
use App\Models\VariantOption;
use Illuminate\Support\Facades\Config;

it('adds an option value to an axis', function (): void {
    $seller = $this->seller();
    $listing = $this->listing($seller);
    $axis = OptionAxis::factory()->create(['listing_id' => $listing->id]);

    $response = $this->actingAs($seller, 'seller')->post("/seller/listings/{$listing->id}/option-axes/{$axis->id}/option-values", [
        'label' => 'Gold',
        'surcharge' => '5.00',
        'is_default' => '1',
        'position' => 0,
    ]);

    $response->assertRedirect(route('seller.listings.option-axes.index', $listing));
    $value = OptionValue::where('axis_id', $axis->id)->sole();
    expect($value->label)->toBe('Gold')
        ->and($value->surcharge_cents)->toBe(500)
        ->and($value->is_default)->toBeTrue();
});

it('refuses adding an option value to another sellers listing', function (): void {
    $listing = $this->listing($this->seller('Other Studio'));
    $axis = OptionAxis::factory()->create(['listing_id' => $listing->id]);

    $response = $this->actingAs($this->seller(), 'seller')->post("/seller/listings/{$listing->id}/option-axes/{$axis->id}/option-values", [
        'label' => 'Gold',
        'surcharge' => '0.00',
        'position' => 0,
    ]);

    $response->assertNotFound();
    expect(OptionValue::count())->toBe(0);
});

it('updates an option value', function (): void {
    $seller = $this->seller();
    $listing = $this->listing($seller);
    $axis = OptionAxis::factory()->create(['listing_id' => $listing->id]);
    $value = OptionValue::factory()->create(['axis_id' => $axis->id, 'label' => 'Gold']);

    $response = $this->actingAs($seller, 'seller')->put("/seller/listings/{$listing->id}/option-axes/{$axis->id}/option-values/{$value->id}", [
        'label' => 'Rose Gold',
        'surcharge' => '-2.50',
        'position' => 1,
    ]);

    $response->assertRedirect(route('seller.listings.option-axes.index', $listing));
    expect($value->fresh()?->label)->toBe('Rose Gold')
        ->and($value->fresh()?->surcharge_cents)->toBe(-250);
});

it('removes an option value no variant selects', function (): void {
    $seller = $this->seller();
    $listing = $this->listing($seller);
    $axis = OptionAxis::factory()->create(['listing_id' => $listing->id]);
    $value = OptionValue::factory()->create(['axis_id' => $axis->id]);

    $response = $this->actingAs($seller, 'seller')->delete("/seller/listings/{$listing->id}/option-axes/{$axis->id}/option-values/{$value->id}");

    $response->assertRedirect(route('seller.listings.option-axes.index', $listing));
    expect(OptionValue::find($value->id))->toBeNull();
});

it('refuses to remove an option value a variant still selects', function (): void {
    $seller = $this->seller();
    $listing = $this->listing($seller);
    $axis = OptionAxis::factory()->create(['listing_id' => $listing->id]);
    $value = OptionValue::factory()->create(['axis_id' => $axis->id]);
    $variant = Variant::factory()->create(['listing_id' => $listing->id]);
    VariantOption::factory()->create(['variant_id' => $variant->id, 'axis_id' => $axis->id, 'option_value_id' => $value->id]);

    $response = $this->actingAs($seller, 'seller')->delete("/seller/listings/{$listing->id}/option-axes/{$axis->id}/option-values/{$value->id}");

    $response->assertSessionHasErrors();
    expect(OptionValue::find($value->id))->not->toBeNull();
});

it('refuses to remove another sellers option value', function (): void {
    $listing = $this->listing($this->seller('Other Studio'));
    $axis = OptionAxis::factory()->create(['listing_id' => $listing->id]);
    $value = OptionValue::factory()->create(['axis_id' => $axis->id]);

    $response = $this->actingAs($this->seller(), 'seller')->delete("/seller/listings/{$listing->id}/option-axes/{$axis->id}/option-values/{$value->id}");

    $response->assertNotFound();
    expect(OptionValue::find($value->id))->not->toBeNull();
});

it('trips the listing-write limit adding an option value', function (): void {
    Config::set('rate_limits.listing_write', RateLimitValue::parse('1/1h', 'RATE_LIMIT_LISTING_WRITE'));
    $seller = $this->seller();
    $listing = $this->listing($seller);
    $axis = OptionAxis::factory()->create(['listing_id' => $listing->id]);
    $this->actingAs($seller, 'seller')->post("/seller/listings/{$listing->id}/option-axes/{$axis->id}/option-values", ['label' => 'First', 'surcharge' => '0.00', 'position' => 0]);

    $response = $this->actingAs($seller, 'seller')->post("/seller/listings/{$listing->id}/option-axes/{$axis->id}/option-values", ['label' => 'Second', 'surcharge' => '0.00', 'position' => 1]);

    $response->assertStatus(429);
    expect(OptionValue::where('axis_id', $axis->id)->count())->toBe(1);
});

it('trips the listing-write limit updating an option value', function (): void {
    Config::set('rate_limits.listing_write', RateLimitValue::parse('1/1h', 'RATE_LIMIT_LISTING_WRITE'));
    $seller = $this->seller();
    $listing = $this->listing($seller);
    $axis = OptionAxis::factory()->create(['listing_id' => $listing->id]);
    $value = OptionValue::factory()->create(['axis_id' => $axis->id, 'label' => 'Old']);
    $this->actingAs($seller, 'seller')->post("/seller/listings/{$listing->id}/option-axes/{$axis->id}/option-values", ['label' => 'Consumes budget', 'surcharge' => '0.00', 'position' => 0]);

    $response = $this->actingAs($seller, 'seller')->put("/seller/listings/{$listing->id}/option-axes/{$axis->id}/option-values/{$value->id}", ['label' => 'New', 'surcharge' => '0.00', 'position' => 0]);

    $response->assertStatus(429);
    expect($value->fresh()?->label)->toBe('Old');
});

it('trips the listing-write limit removing an option value', function (): void {
    Config::set('rate_limits.listing_write', RateLimitValue::parse('1/1h', 'RATE_LIMIT_LISTING_WRITE'));
    $seller = $this->seller();
    $listing = $this->listing($seller);
    $axis = OptionAxis::factory()->create(['listing_id' => $listing->id]);
    $value = OptionValue::factory()->create(['axis_id' => $axis->id]);
    $this->actingAs($seller, 'seller')->post("/seller/listings/{$listing->id}/option-axes/{$axis->id}/option-values", ['label' => 'Consumes budget', 'surcharge' => '0.00', 'position' => 0]);

    $response = $this->actingAs($seller, 'seller')->delete("/seller/listings/{$listing->id}/option-axes/{$axis->id}/option-values/{$value->id}");

    $response->assertStatus(429);
    expect(OptionValue::find($value->id))->not->toBeNull();
});
