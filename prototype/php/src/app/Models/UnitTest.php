<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Configurator\UnitState;
use App\Domain\DomainRuleViolation;
use Illuminate\Database\Query\Grammars\MySqlGrammar;
use Illuminate\Support\Facades\DB;

it('belongs to its variant and casts its state and specs', function (): void {
    $variant = Variant::factory()->create();
    $unit = Unit::factory()->create([
        'variant_id' => $variant->id,
        'specs_json' => ['height_mm' => 240, 'condition' => 'excellent'],
    ]);

    expect($unit->variant()->first()?->id)->toBe($variant->id)
        ->and($unit->state)->toBe(UnitState::Available)
        ->and($unit->specs_json)->toBe(['height_mm' => 240, 'condition' => 'excellent']);
});

it('moves through reserved and sold', function (): void {
    expect(Unit::factory()->reserved()->create()->state)->toBe(UnitState::Reserved)
        ->and(Unit::factory()->sold()->create()->state)->toBe(UnitState::Sold);
});

it('sells an available unit', function (): void {
    $unit = Unit::factory()->create();

    expect($unit->sell()->state)->toBe(UnitState::Sold)
        ->and($unit->refresh()->state)->toBe(UnitState::Sold);
});

it('rejects selling a unit that is not available', function (): void {
    $unit = Unit::factory()->sold()->create();

    expect(fn () => $unit->sell())->toThrow(DomainRuleViolation::class);
});

it('restocks a sold unit', function (): void {
    $unit = Unit::factory()->sold()->create();

    expect($unit->restock()->state)->toBe(UnitState::Available)
        ->and($unit->refresh()->state)->toBe(UnitState::Available);
});

it('rejects restocking a unit that was not sold', function (): void {
    $unit = Unit::factory()->create();

    expect(fn () => $unit->restock())->toThrow(DomainRuleViolation::class);
});

it('takes the row placement reads for update, in id order', function (): void {
    // SQLite has no row lock and its grammar compiles the clause away, so the
    // query is compiled here with the grammar of a database that does have
    // one — what the same read asks for in production.
    $query = Unit::query()->lockedForPlacement()->toBase();

    expect((new MySqlGrammar(DB::connection()))->compileSelect($query))
        ->toContain('order by `id` asc')
        ->toEndWith('for update');
});
