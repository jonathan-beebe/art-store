<?php

declare(strict_types=1);

namespace App\Actions\Configurator;

use App\Models\Modifier;
use App\Models\OptionValue;

it('sets a modifier’s scope to the given option values', function (): void {
    $modifier = Modifier::factory()->create();
    $personalized = OptionValue::factory()->create();

    $scopes = app(SetModifierScope::class)($modifier, [$personalized]);

    expect($scopes)->toHaveCount(1)
        ->and($modifier->appliesTo([$personalized->id]))->toBeTrue()
        ->and($modifier->appliesTo(['ovl_someone_else']))->toBeFalse();
});

it('drops a value unchecked from a previous scope', function (): void {
    $modifier = Modifier::factory()->create();
    $engraved = OptionValue::factory()->create();
    $personalized = OptionValue::factory()->create();
    app(SetModifierScope::class)($modifier, [$engraved, $personalized]);

    app(SetModifierScope::class)($modifier, [$personalized]);

    expect($modifier->scopes()->pluck('option_value_id')->all())->toBe([$personalized->id]);
});

it('clears every scope when nothing is selected, so the modifier shows product-wide again', function (): void {
    $modifier = Modifier::factory()->create();
    app(SetModifierScope::class)($modifier, [OptionValue::factory()->create()]);

    app(SetModifierScope::class)($modifier, []);

    expect($modifier->scopes()->count())->toBe(0)
        ->and($modifier->appliesTo([]))->toBeTrue();
});

it('is idempotent for a value already scoped', function (): void {
    $modifier = Modifier::factory()->create();
    $value = OptionValue::factory()->create();

    app(SetModifierScope::class)($modifier, [$value]);
    app(SetModifierScope::class)($modifier, [$value]);

    expect($modifier->scopes()->count())->toBe(1);
});
