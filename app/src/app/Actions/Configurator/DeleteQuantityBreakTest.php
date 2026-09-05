<?php

declare(strict_types=1);

namespace App\Actions\Configurator;

use App\Models\QuantityBreak;

it('deletes a quantity break tier', function (): void {
    $break = QuantityBreak::factory()->create();

    app(DeleteQuantityBreak::class)($break);

    expect(QuantityBreak::find($break->id))->toBeNull();
});
