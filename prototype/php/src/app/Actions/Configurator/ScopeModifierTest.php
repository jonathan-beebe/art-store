<?php

declare(strict_types=1);

namespace App\Actions\Configurator;

use App\Models\Modifier;
use App\Models\OptionValue;

it('scopes a modifier to the given option values', function (): void {
    $modifier = Modifier::factory()->create();
    $personalized = OptionValue::factory()->create();
    $engraved = OptionValue::factory()->create();

    $scopes = app(ScopeModifier::class)($modifier, [$personalized, $engraved]);

    expect($scopes)->toHaveCount(2)
        ->and($modifier->appliesTo([$personalized->id]))->toBeTrue()
        ->and($modifier->appliesTo(['ovl_someone_else']))->toBeFalse();
});

it('is idempotent for a value already scoped', function (): void {
    $modifier = Modifier::factory()->create();
    $value = OptionValue::factory()->create();

    app(ScopeModifier::class)($modifier, [$value]);
    app(ScopeModifier::class)($modifier, [$value]);

    expect($modifier->scopes()->count())->toBe(1);
});
