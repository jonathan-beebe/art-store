<?php

declare(strict_types=1);

namespace App\Models;

it('belongs to its listing and converts to the domain value object', function (): void {
    $listing = $this->listing($this->seller());
    $break = QuantityBreak::factory()->create(['listing_id' => $listing->id, 'min_qty' => 50, 'discount_bps' => 500]);

    expect($break->listing()->first()?->id)->toBe($listing->id)
        ->and($break->toDomain()->minQty)->toBe(50)
        ->and($break->toDomain()->discountBps)->toBe(500);
});
