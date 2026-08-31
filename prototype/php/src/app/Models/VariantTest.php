<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\DomainRuleViolation;
use App\Domain\Money\Money;
use Illuminate\Database\Query\Grammars\MySqlGrammar;
use Illuminate\Support\Facades\DB;

it('belongs to a listing and lists its options and units', function (): void {
    $listing = $this->listing($this->seller());
    $variant = Variant::factory()->create(['listing_id' => $listing->id]);
    VariantOption::factory()->create(['variant_id' => $variant->id]);
    Unit::factory()->create(['variant_id' => $variant->id]);

    expect($variant->listing()->first()?->id)->toBe($listing->id)
        ->and($variant->options()->count())->toBe(1)
        ->and($variant->units()->count())->toBe(1);
});

it('resolves its price as the base plus its option surcharges', function (): void {
    $variant = Variant::factory()->create();
    $axis = OptionAxis::factory()->create(['listing_id' => $variant->listing_id]);
    $value = OptionValue::factory()->surcharging(500)->create(['axis_id' => $axis->id]);
    VariantOption::factory()->create(['variant_id' => $variant->id, 'axis_id' => $axis->id, 'option_value_id' => $value->id]);

    expect($variant->resolvedPrice(Money::fromCents(2000))->cents)->toBe(2500);
});

it('resolves its price to the override, ignoring surcharges', function (): void {
    $variant = Variant::factory()->overriddenAt(9900)->create();
    $axis = OptionAxis::factory()->create(['listing_id' => $variant->listing_id]);
    $value = OptionValue::factory()->surcharging(500)->create(['axis_id' => $axis->id]);
    VariantOption::factory()->create(['variant_id' => $variant->id, 'axis_id' => $axis->id, 'option_value_id' => $value->id]);

    expect($variant->resolvedPrice(Money::fromCents(2000))->cents)->toBe(9900);
});

it('joins its option labels, in choice order, as its combo label', function (): void {
    $variant = Variant::factory()->create();
    $house = OptionAxis::factory()->create(['listing_id' => $variant->listing_id]);
    $size = OptionAxis::factory()->create(['listing_id' => $variant->listing_id]);
    $gryffindor = OptionValue::factory()->create(['axis_id' => $house->id, 'label' => 'Gryffindor']);
    $large = OptionValue::factory()->create(['axis_id' => $size->id, 'label' => 'Large']);
    VariantOption::factory()->create(['variant_id' => $variant->id, 'axis_id' => $house->id, 'option_value_id' => $gryffindor->id]);
    VariantOption::factory()->create(['variant_id' => $variant->id, 'axis_id' => $size->id, 'option_value_id' => $large->id]);

    $reloaded = Variant::query()->with('options.optionValue')->findOrFail($variant->id);

    expect($reloaded->comboLabel())->toBe('Gryffindor · Large');
});

it('names an option-less combo label generically', function (): void {
    $variant = Variant::factory()->create();

    expect($variant->comboLabel())->toBe('This combination');
});

it('counts only its available units', function (): void {
    $variant = Variant::factory()->serialized()->create();
    Unit::factory()->count(2)->create(['variant_id' => $variant->id]);
    Unit::factory()->sold()->create(['variant_id' => $variant->id]);

    expect($variant->availableUnitCount())->toBe(2);
});

it('resolves availability through the domain rule', function (): void {
    $available = Variant::factory()->create(['quantity' => 3]);
    $disabled = Variant::factory()->disabled()->create();

    expect($available->availability()->available)->toBeTrue()
        ->and($disabled->availability()->available)->toBeFalse();
});

it('reads the axes it covers from its options', function (): void {
    $variant = Variant::factory()->create();
    $axisOne = OptionAxis::factory()->create(['listing_id' => $variant->listing_id]);
    $axisTwo = OptionAxis::factory()->create(['listing_id' => $variant->listing_id]);
    VariantOption::factory()->create(['variant_id' => $variant->id, 'axis_id' => $axisOne->id]);
    VariantOption::factory()->create(['variant_id' => $variant->id, 'axis_id' => $axisTwo->id]);

    expect($variant->axisIdsCovered())->toEqualCanonicalizing([$axisOne->id, $axisTwo->id]);
});

it('decrements its tracked quantity by a sale', function (): void {
    $variant = Variant::factory()->create(['quantity' => 3]);

    expect($variant->decrementQuantity(2)->quantity)->toBe(1)
        ->and($variant->refresh()->quantity)->toBe(1);
});

it('leaves an untracked quantity null through a sale', function (): void {
    $variant = Variant::factory()->serialized()->create();

    expect($variant->decrementQuantity(1)->quantity)->toBeNull();
});

it('rejects a sale for more than its tracked quantity holds', function (): void {
    $variant = Variant::factory()->create(['quantity' => 1]);

    expect(fn () => $variant->decrementQuantity(2))->toThrow(DomainRuleViolation::class);
});

it('restores its tracked quantity by a restock', function (): void {
    $variant = Variant::factory()->create(['quantity' => 1]);

    expect($variant->restoreQuantity(2)->quantity)->toBe(3)
        ->and($variant->refresh()->quantity)->toBe(3);
});

it('leaves an untracked quantity null through a restock', function (): void {
    $variant = Variant::factory()->serialized()->create();

    expect($variant->restoreQuantity(1)->quantity)->toBeNull();
});

it('takes the rows placement reads for update, in id order', function (): void {
    // SQLite has no row lock and its grammar compiles the clause away, so the
    // query is compiled here with the grammar of a database that does have
    // one — what the same read asks for in production.
    $query = Variant::query()->lockedForPlacement()->toBase();

    expect((new MySqlGrammar(DB::connection()))->compileSelect($query))
        ->toContain('order by `id` asc')
        ->toEndWith('for update');
});
