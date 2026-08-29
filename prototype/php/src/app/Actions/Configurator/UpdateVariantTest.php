<?php

declare(strict_types=1);

namespace App\Actions\Configurator;

use App\Models\Variant;

it('overrides a variant’s price, quantity, serialization, and enablement', function (): void {
    $variant = Variant::factory()->create();

    $updated = app(UpdateVariant::class)($variant, 15000, 4, false, true);

    expect($updated->price_override_cents)->toBe(15000)
        ->and($updated->quantity)->toBe(4)
        ->and($updated->enabled)->toBeTrue()
        ->and($updated->sku)->toBeNull();
});

it('sets a sku', function (): void {
    $variant = Variant::factory()->withSku('OLD-SKU')->create();

    $updated = app(UpdateVariant::class)($variant, null, 1, false, true, 'RING-GOLD-BOTH');

    expect($updated->sku)->toBe('RING-GOLD-BOTH');
});

it('clears quantity when the update turns a variant serialized', function (): void {
    $variant = Variant::factory()->create(['quantity' => 3]);

    $updated = app(UpdateVariant::class)($variant, null, 3, true, true);

    expect($updated->quantity)->toBeNull()
        ->and($updated->is_serialized)->toBeTrue();
});

it('disables a variant', function (): void {
    $variant = Variant::factory()->create();

    expect(app(UpdateVariant::class)($variant, null, 1, false, false)->enabled)->toBeFalse();
});
