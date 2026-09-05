<?php

declare(strict_types=1);

namespace App\Actions\Configurator;

use App\Models\Modifier;

it('adds a priced option to a select modifier', function (): void {
    $modifier = Modifier::factory()->select()->create();

    $option = app(AddModifierOption::class)($modifier, 'Pearl Shimmer', 50, 1);

    expect($option->modifier_id)->toBe($modifier->id)
        ->and($option->label)->toBe('Pearl Shimmer')
        ->and($option->add_on_price_cents)->toBe(50)
        ->and($option->position)->toBe(1);
});

it('defaults to no add-on price', function (): void {
    expect(app(AddModifierOption::class)(Modifier::factory()->select()->create(), 'Standard')->add_on_price_cents)->toBe(0);
});
