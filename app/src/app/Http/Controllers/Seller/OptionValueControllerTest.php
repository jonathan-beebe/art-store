<?php

declare(strict_types=1);

namespace App\Http\Controllers\Seller;

use App\Domain\RateLimiting\RateLimitValue;
use App\Models\OptionAxis;
use App\Models\OptionValue;
use App\Models\Variant;
use App\Models\VariantOption;
use Illuminate\Support\Facades\Config;
use LogicException;

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

it('answers not found updating an option value from another axis', function (): void {
    $seller = $this->seller();
    $listing = $this->listing($seller);
    $axis = OptionAxis::factory()->create(['listing_id' => $listing->id]);
    $otherAxis = OptionAxis::factory()->create(['listing_id' => $listing->id]);
    $value = OptionValue::factory()->create(['axis_id' => $otherAxis->id]);

    $response = $this->actingAs($seller, 'seller')->put("/seller/listings/{$listing->id}/option-axes/{$axis->id}/option-values/{$value->id}", [
        'label' => 'New',
        'surcharge' => '0.00',
        'position' => 0,
    ]);

    $response->assertNotFound();
});

it('unsets the previous default when saving another option as preselected', function (): void {
    $seller = $this->seller();
    $listing = $this->listing($seller, ['slug' => 'two-tone-ring']);
    $axis = OptionAxis::factory()->create(['listing_id' => $listing->id]);
    $a = OptionValue::factory()->create(['axis_id' => $axis->id, 'label' => 'Gold', 'is_default' => true, 'position' => 0]);
    $b = OptionValue::factory()->create(['axis_id' => $axis->id, 'label' => 'Silver', 'is_default' => false, 'position' => 1]);

    $response = $this->actingAs($seller, 'seller')->put("/seller/listings/{$listing->id}/option-axes/{$axis->id}/option-values/{$b->id}", [
        'label' => $b->label,
        'surcharge' => '0.00',
        'is_default' => '1',
        'position' => $b->position,
    ]);

    $response->assertRedirect(route('seller.listings.option-axes.index', $listing));
    expect($a->fresh()?->is_default)->toBeFalse()
        ->and($b->fresh()?->is_default)->toBeTrue()
        ->and(OptionValue::where('axis_id', $axis->id)->where('is_default', true)->count())->toBe(1);

    $buyerPage = $this->get('/art/two-tone-ring');
    $buyerPage->assertSee('value="'.$b->id.'" selected', false);
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

it('trips the listing-write limit', function (string $action): void {
    Config::set('rate_limits.listing_write', RateLimitValue::parse('1/1h', 'RATE_LIMIT_LISTING_WRITE'));
    $seller = $this->seller();
    $listing = $this->listing($seller);
    $axis = OptionAxis::factory()->create(['listing_id' => $listing->id]);
    $value = OptionValue::factory()->create(['axis_id' => $axis->id, 'label' => 'Old']);
    $this->actingAs($seller, 'seller')->post("/seller/listings/{$listing->id}/option-axes/{$axis->id}/option-values", ['label' => 'Consumes budget', 'surcharge' => '0.00', 'position' => 0]);

    $response = match ($action) {
        'adding' => $this->actingAs($seller, 'seller')->post("/seller/listings/{$listing->id}/option-axes/{$axis->id}/option-values", ['label' => 'Second', 'surcharge' => '0.00', 'position' => 1]),
        'updating' => $this->actingAs($seller, 'seller')->put("/seller/listings/{$listing->id}/option-axes/{$axis->id}/option-values/{$value->id}", ['label' => 'New', 'surcharge' => '0.00', 'position' => 0]),
        'removing' => $this->actingAs($seller, 'seller')->delete("/seller/listings/{$listing->id}/option-axes/{$axis->id}/option-values/{$value->id}"),
        default => throw new LogicException("Unknown action: {$action}"),
    };

    $response->assertStatus(429);
    match ($action) {
        'adding' => expect(OptionValue::where('axis_id', $axis->id)->count())->toBe(2),
        'updating' => expect($value->fresh()?->label)->toBe('Old'),
        'removing' => expect(OptionValue::find($value->id))->not->toBeNull(),
    };
})->with(['adding', 'updating', 'removing']);
