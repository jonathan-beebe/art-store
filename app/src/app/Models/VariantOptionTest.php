<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\QueryException;

it('rejects a second value on the same axis for the same variant', function (): void {
    $variant = Variant::factory()->create();
    $axis = OptionAxis::factory()->create();
    VariantOption::factory()->create(['variant_id' => $variant->id, 'axis_id' => $axis->id]);

    expect(fn () => VariantOption::factory()->create(['variant_id' => $variant->id, 'axis_id' => $axis->id]))
        ->toThrow(QueryException::class);
});
