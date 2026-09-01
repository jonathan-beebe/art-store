<?php

declare(strict_types=1);

namespace App\Http\Controllers\Seller;

use App\Domain\RateLimiting\RateLimitValue;
use App\Models\Modifier;
use App\Models\ModifierOption;
use Illuminate\Support\Facades\Config;
use LogicException;

it('adds an option to a select modifier', function (): void {
    $seller = $this->seller();
    $listing = $this->listing($seller);
    $modifier = Modifier::factory()->select()->create(['listing_id' => $listing->id]);

    $response = $this->actingAs($seller, 'seller')->post("/seller/listings/{$listing->id}/modifiers/{$modifier->id}/options", [
        'label' => 'Serif',
        'add_on_price' => '3.00',
        'position' => 0,
    ]);

    $response->assertRedirect(route('seller.listings.modifiers.index', $listing));
    $option = ModifierOption::where('modifier_id', $modifier->id)->sole();
    expect($option->label)->toBe('Serif')
        ->and($option->add_on_price_cents)->toBe(300);
});

it('updates a modifier option', function (): void {
    $seller = $this->seller();
    $listing = $this->listing($seller);
    $modifier = Modifier::factory()->select()->create(['listing_id' => $listing->id]);
    $option = ModifierOption::factory()->create(['modifier_id' => $modifier->id, 'label' => 'Serif']);

    $response = $this->actingAs($seller, 'seller')->put("/seller/listings/{$listing->id}/modifiers/{$modifier->id}/options/{$option->id}", [
        'label' => 'Script',
        'add_on_price' => '4.50',
        'position' => 2,
    ]);

    $response->assertRedirect(route('seller.listings.modifiers.index', $listing));
    $updated = $option->fresh();
    expect($updated?->label)->toBe('Script')
        ->and($updated?->add_on_price_cents)->toBe(450);
});

it('answers not found updating a modifier option from another modifier', function (): void {
    $seller = $this->seller();
    $listing = $this->listing($seller);
    $modifier = Modifier::factory()->select()->create(['listing_id' => $listing->id]);
    $otherModifier = Modifier::factory()->select()->create(['listing_id' => $listing->id]);
    $option = ModifierOption::factory()->create(['modifier_id' => $otherModifier->id]);

    $response = $this->actingAs($seller, 'seller')->put("/seller/listings/{$listing->id}/modifiers/{$modifier->id}/options/{$option->id}", [
        'label' => 'New',
        'add_on_price' => '0.00',
        'position' => 0,
    ]);

    $response->assertNotFound();
});

it('removes a modifier option', function (): void {
    $seller = $this->seller();
    $listing = $this->listing($seller);
    $modifier = Modifier::factory()->select()->create(['listing_id' => $listing->id]);
    $option = ModifierOption::factory()->create(['modifier_id' => $modifier->id]);

    $response = $this->actingAs($seller, 'seller')->delete("/seller/listings/{$listing->id}/modifiers/{$modifier->id}/options/{$option->id}");

    $response->assertRedirect(route('seller.listings.modifiers.index', $listing));
    expect(ModifierOption::find($option->id))->toBeNull();
});

it('trips the listing-write limit', function (string $action): void {
    Config::set('rate_limits.listing_write', RateLimitValue::parse('1/1h', 'RATE_LIMIT_LISTING_WRITE'));
    $seller = $this->seller();
    $listing = $this->listing($seller);
    $modifier = Modifier::factory()->select()->create(['listing_id' => $listing->id]);
    $option = ModifierOption::factory()->create(['modifier_id' => $modifier->id, 'label' => 'Old']);
    $this->actingAs($seller, 'seller')->post("/seller/listings/{$listing->id}/modifiers/{$modifier->id}/options", ['label' => 'Consumes budget', 'add_on_price' => '0.00', 'position' => 0]);

    $response = match ($action) {
        'adding' => $this->actingAs($seller, 'seller')->post("/seller/listings/{$listing->id}/modifiers/{$modifier->id}/options", ['label' => 'Second', 'add_on_price' => '0.00', 'position' => 1]),
        'updating' => $this->actingAs($seller, 'seller')->put("/seller/listings/{$listing->id}/modifiers/{$modifier->id}/options/{$option->id}", ['label' => 'New', 'add_on_price' => '0.00', 'position' => 0]),
        'removing' => $this->actingAs($seller, 'seller')->delete("/seller/listings/{$listing->id}/modifiers/{$modifier->id}/options/{$option->id}"),
        default => throw new LogicException("Unknown action: {$action}"),
    };

    $response->assertStatus(429);
    match ($action) {
        'adding' => expect(ModifierOption::where('modifier_id', $modifier->id)->count())->toBe(2),
        'updating' => expect($option->fresh()?->label)->toBe('Old'),
        'removing' => expect(ModifierOption::find($option->id))->not->toBeNull(),
    };
})->with(['adding', 'updating', 'removing']);
