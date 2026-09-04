<?php

declare(strict_types=1);

namespace App\Actions\Configurator;

use App\Models\QuantityBreak;
use InvalidArgumentException;

it('updates a quantity break tier', function (): void {
    $break = QuantityBreak::factory()->create(['min_qty' => 10, 'discount_bps' => 500]);

    $updated = app(UpdateQuantityBreak::class)($break, 20, 1000);

    expect($updated->min_qty)->toBe(20)
        ->and($updated->discount_bps)->toBe(1000);
});

it('refuses a tier the domain would refuse', function (): void {
    app(UpdateQuantityBreak::class)(QuantityBreak::factory()->create(), 1, 500);
})->throws(InvalidArgumentException::class);
