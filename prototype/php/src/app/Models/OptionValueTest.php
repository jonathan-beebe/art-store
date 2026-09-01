<?php

declare(strict_types=1);

namespace App\Models;

it('carries its surcharge as money, and defaults to none', function (): void {
    expect(OptionValue::factory()->surcharging(850)->create()->surcharge()->cents)->toBe(850)
        ->and(OptionValue::factory()->create()->surcharge()->cents)->toBe(0)
        ->and(OptionValue::factory()->default()->create()->is_default)->toBeTrue();
});
