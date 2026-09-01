<?php

declare(strict_types=1);

namespace App\Models;

it('carries its surcharge as money, and defaults to none', function (): void {
    expect(OptionValue::factory()->surcharging(850)->create()->surcharge()->cents)->toBe(850)
        ->and(OptionValue::factory()->create()->surcharge()->cents)->toBe(0)
        ->and(OptionValue::factory()->default()->create()->is_default)->toBeTrue();
});

it('reads its own price, defaulting to zero when unset', function (): void {
    expect(OptionValue::factory()->create(['price_cents' => null])->price()->cents)->toBe(0)
        ->and(OptionValue::factory()->create(['price_cents' => 4200])->price()->cents)->toBe(4200);
});
