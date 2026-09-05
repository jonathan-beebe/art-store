<?php

declare(strict_types=1);

namespace App\Models;

it('carries its add-on as money', function (): void {
    $option = ModifierOption::factory()->pricedAt(200)->create();

    expect($option->addOn()->cents)->toBe(200);
});
