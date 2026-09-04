<?php

declare(strict_types=1);

namespace App\Support\Configurator;

use App\Models\Unit;
use App\Models\Variant;

it('tallies a mix of available, sold, and on-hold pieces', function (): void {
    $variant = Variant::factory()->serialized()->create();
    Unit::factory()->count(2)->create(['variant_id' => $variant->id]);
    Unit::factory()->sold()->create(['variant_id' => $variant->id]);
    Unit::factory()->reserved()->create(['variant_id' => $variant->id]);

    $counts = UnitStateCounts::tally($variant->units()->get());

    expect($counts)->toBe(['total' => 4, 'available' => 2, 'sold' => 1, 'onHold' => 1]);
});

it('tallies an empty set of pieces as all zeroes', function (): void {
    expect(UnitStateCounts::tally([]))->toBe(['total' => 0, 'available' => 0, 'sold' => 0, 'onHold' => 0]);
});
