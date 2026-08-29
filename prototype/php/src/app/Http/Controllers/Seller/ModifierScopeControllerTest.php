<?php

declare(strict_types=1);

namespace App\Http\Controllers\Seller;

use App\Domain\RateLimiting\RateLimitValue;
use App\Models\Modifier;
use App\Models\OptionAxis;
use App\Models\OptionValue;
use Illuminate\Support\Facades\Config;

it('sets a modifier’s scope to the checked option values', function (): void {
    $seller = $this->seller();
    $listing = $this->listing($seller);
    $axis = OptionAxis::factory()->create(['listing_id' => $listing->id]);
    $personalized = OptionValue::factory()->create(['axis_id' => $axis->id]);
    $modifier = Modifier::factory()->create(['listing_id' => $listing->id]);

    $response = $this->actingAs($seller, 'seller')->post("/seller/listings/{$listing->id}/modifiers/{$modifier->id}/scope", [
        'option_value_id' => [$personalized->id],
    ]);

    $response->assertRedirect(route('seller.listings.modifiers.index', $listing));
    expect($modifier->scopes()->pluck('option_value_id')->all())->toBe([$personalized->id]);
});

it('clears the scope when Always is chosen even if boxes are still checked', function (): void {
    $seller = $this->seller();
    $listing = $this->listing($seller);
    $axis = OptionAxis::factory()->create(['listing_id' => $listing->id]);
    $value = OptionValue::factory()->create(['axis_id' => $axis->id]);
    $modifier = Modifier::factory()->create(['listing_id' => $listing->id]);

    $response = $this->actingAs($seller, 'seller')->post("/seller/listings/{$listing->id}/modifiers/{$modifier->id}/scope", [
        'when' => 'always',
        'option_value_id' => [$value->id],
    ]);

    $response->assertRedirect(route('seller.listings.modifiers.index', $listing));
    expect($modifier->scopes()->count())->toBe(0);
});

it('clears the scope with nothing checked', function (): void {
    $seller = $this->seller();
    $listing = $this->listing($seller);
    $axis = OptionAxis::factory()->create(['listing_id' => $listing->id]);
    $value = OptionValue::factory()->create(['axis_id' => $axis->id]);
    $modifier = Modifier::factory()->create(['listing_id' => $listing->id]);
    $this->actingAs($seller, 'seller')->post("/seller/listings/{$listing->id}/modifiers/{$modifier->id}/scope", ['option_value_id' => [$value->id]]);

    $this->actingAs($seller, 'seller')->post("/seller/listings/{$listing->id}/modifiers/{$modifier->id}/scope", []);

    expect($modifier->scopes()->count())->toBe(0);
});

it('refuses an option value outside the listing', function (): void {
    $seller = $this->seller();
    $listing = $this->listing($seller);
    $otherListing = $this->listing($seller);
    $otherAxis = OptionAxis::factory()->create(['listing_id' => $otherListing->id]);
    $otherValue = OptionValue::factory()->create(['axis_id' => $otherAxis->id]);
    $modifier = Modifier::factory()->create(['listing_id' => $listing->id]);

    $response = $this->actingAs($seller, 'seller')->post("/seller/listings/{$listing->id}/modifiers/{$modifier->id}/scope", [
        'option_value_id' => [$otherValue->id],
    ]);

    $response->assertSessionHasErrors('option_value_id.0');
});

it('answers not found scoping a modifier that belongs to a different listing', function (): void {
    $seller = $this->seller();
    $listing = $this->listing($seller);
    $otherListing = $this->listing($seller);
    $modifier = Modifier::factory()->create(['listing_id' => $otherListing->id]);

    $response = $this->actingAs($seller, 'seller')->post("/seller/listings/{$listing->id}/modifiers/{$modifier->id}/scope", []);

    $response->assertNotFound();
});

it('refuses scoping another sellers modifier', function (): void {
    $listing = $this->listing($this->seller('Other Studio'));
    $modifier = Modifier::factory()->create(['listing_id' => $listing->id]);

    $response = $this->actingAs($this->seller(), 'seller')->post("/seller/listings/{$listing->id}/modifiers/{$modifier->id}/scope", []);

    $response->assertNotFound();
});

it('C9: a Version choice with a question scoped to it shows or hides the question on the buyer page', function (): void {
    $seller = $this->seller();
    $listing = $this->listing($seller, ['slug' => 'mug', 'price_cents' => 1800]);
    $this->actingAs($seller, 'seller')->post("/seller/listings/{$listing->id}/option-axes", ['name' => 'Version', 'position' => 0]);
    $axis = OptionAxis::where('listing_id', $listing->id)->sole();
    $this->actingAs($seller, 'seller')->post("/seller/listings/{$listing->id}/option-axes/{$axis->id}/option-values", [
        'label' => 'Blank', 'surcharge' => '0.00', 'is_default' => '1', 'position' => 0,
    ]);
    $this->actingAs($seller, 'seller')->post("/seller/listings/{$listing->id}/option-axes/{$axis->id}/option-values", [
        'label' => 'Decorated', 'surcharge' => '3.00', 'position' => 1,
    ]);
    $blank = OptionValue::where('axis_id', $axis->id)->where('label', 'Blank')->sole();
    $decorated = OptionValue::where('axis_id', $axis->id)->where('label', 'Decorated')->sole();
    $this->actingAs($seller, 'seller')->post("/seller/listings/{$listing->id}/variants/generate");
    $this->actingAs($seller, 'seller')->post("/seller/listings/{$listing->id}/modifiers", [
        'kind' => 'text', 'prompt' => 'What should we letter?', 'position' => 0, 'required' => '1', 'char_limit' => 16,
    ]);
    $modifier = Modifier::where('listing_id', $listing->id)->sole();
    $this->actingAs($seller, 'seller')->post("/seller/listings/{$listing->id}/modifiers/{$modifier->id}/scope", [
        'option_value_id' => [$decorated->id],
    ]);

    $blankPage = $this->get('/art/mug?'.http_build_query(['axis' => [$axis->id => $blank->id]]));
    $blankPage->assertOk();
    $blankPage->assertDontSee('What should we letter?');

    $decoratedPage = $this->get('/art/mug?'.http_build_query(['axis' => [$axis->id => $decorated->id]]));
    $decoratedPage->assertOk();
    $decoratedPage->assertSee('What should we letter?');
});

it('trips the listing-write limit setting a scope', function (): void {
    Config::set('rate_limits.listing_write', RateLimitValue::parse('1/1h', 'RATE_LIMIT_LISTING_WRITE'));
    $seller = $this->seller();
    $listing = $this->listing($seller);
    $axis = OptionAxis::factory()->create(['listing_id' => $listing->id]);
    $value = OptionValue::factory()->create(['axis_id' => $axis->id]);
    $modifier = Modifier::factory()->create(['listing_id' => $listing->id]);
    $this->actingAs($seller, 'seller')->post("/seller/listings/{$listing->id}/modifiers/{$modifier->id}/scope", ['option_value_id' => [$value->id]]);

    $response = $this->actingAs($seller, 'seller')->post("/seller/listings/{$listing->id}/modifiers/{$modifier->id}/scope", []);

    $response->assertStatus(429);
    expect($modifier->scopes()->count())->toBe(1);
});
