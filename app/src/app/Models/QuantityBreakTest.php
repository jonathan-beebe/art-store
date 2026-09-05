<?php

declare(strict_types=1);

namespace App\Models;

it('converts to the domain value object', function (): void {
    $break = QuantityBreak::factory()->create(['min_qty' => 50, 'discount_bps' => 500]);

    expect($break->toDomain()->minQty)->toBe(50)
        ->and($break->toDomain()->discountBps)->toBe(500);
});
