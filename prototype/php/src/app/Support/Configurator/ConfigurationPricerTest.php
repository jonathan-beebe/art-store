<?php

declare(strict_types=1);

namespace App\Support\Configurator;

use App\Models\Modifier;
use App\Models\ModifierOption;
use App\Models\ModifierScope;
use App\Models\OptionAxis;
use App\Models\OptionValue;
use App\Models\QuantityBreak;
use App\Models\Unit;
use App\Models\Variant;

it('prices the base listing alone with no selection', function (): void {
    $listing = $this->listing($this->seller(), ['price_cents' => 2000]);

    $breakdown = ConfigurationPricer::price($listing, [], null, null, [], 1);

    expect($breakdown->lines)->toHaveCount(1)
        ->and($breakdown->lines[0]->label)->toBe('Base price')
        ->and($breakdown->total()->cents)->toBe(2000);
});

it('adds a line for each selected option value that surcharges', function (): void {
    $listing = $this->listing($this->seller(), ['price_cents' => 2000]);
    $axis = OptionAxis::factory()->create(['listing_id' => $listing->id]);
    $free = OptionValue::factory()->create(['axis_id' => $axis->id, 'label' => 'Gold', 'surcharge_cents' => 0]);
    $priced = OptionValue::factory()->create(['axis_id' => $axis->id, 'label' => 'Rose Gold', 'surcharge_cents' => 800]);

    $breakdown = ConfigurationPricer::price($listing, [$priced], null, null, [], 1);

    expect($breakdown->lines)->toHaveCount(2)
        ->and($breakdown->lines[1]->label)->toBe('Rose Gold')
        ->and($breakdown->total()->cents)->toBe(2800);

    $breakdownNoSurcharge = ConfigurationPricer::price($listing, [$free], null, null, [], 1);
    expect($breakdownNoSurcharge->lines)->toHaveCount(1);
});

it('uses the variant override instead of base plus surcharges', function (): void {
    $listing = $this->listing($this->seller(), ['price_cents' => 2000]);
    $variant = Variant::factory()->overriddenAt(9500)->create(['listing_id' => $listing->id]);
    $axis = OptionAxis::factory()->create(['listing_id' => $listing->id]);
    $value = OptionValue::factory()->create(['axis_id' => $axis->id, 'surcharge_cents' => 500]);

    $breakdown = ConfigurationPricer::price($listing, [$value], $variant, null, [], 1);

    expect($breakdown->lines)->toHaveCount(1)
        ->and($breakdown->total()->cents)->toBe(9500);
});

it('uses the unit override before the variant override', function (): void {
    $listing = $this->listing($this->seller(), ['price_cents' => 2000]);
    $variant = Variant::factory()->serialized()->overriddenAt(9500)->create(['listing_id' => $listing->id]);
    $unit = Unit::factory()->create(['variant_id' => $variant->id, 'price_override_cents' => 3500]);

    $breakdown = ConfigurationPricer::price($listing, [], $variant, $unit, [], 1);

    expect($breakdown->total()->cents)->toBe(3500);
});

it('prices a select answer at the chosen option add-on, labeled with its prompt and choice', function (): void {
    $listing = $this->listing($this->seller());
    $modifier = Modifier::factory()->select()->create(['listing_id' => $listing->id, 'prompt' => 'Engraving Font']);
    $option = ModifierOption::factory()->pricedAt(200)->create(['modifier_id' => $modifier->id, 'label' => 'Script']);

    $breakdown = ConfigurationPricer::price($listing, [], null, null, [$modifier->id => $option->id], 1);

    expect($breakdown->lines)->toHaveCount(2)
        ->and($breakdown->lines[1]->label)->toBe('Engraving Font: Script')
        ->and($breakdown->lines[1]->amount->cents)->toBe(200);
});

it('skips a zero-priced text modifier line even when answered', function (): void {
    $listing = $this->listing($this->seller());
    $modifier = Modifier::factory()->create(['listing_id' => $listing->id]);

    $breakdown = ConfigurationPricer::price($listing, [], null, null, [$modifier->id => 'Congrats!'], 1);

    expect($breakdown->lines)->toHaveCount(1);
});

it('prices a flat text modifier once answered', function (): void {
    $listing = $this->listing($this->seller());
    $modifier = Modifier::factory()->create(['listing_id' => $listing->id, 'add_on_price_cents' => 500]);

    $answered = ConfigurationPricer::price($listing, [], null, null, [$modifier->id => 'Congrats!'], 1);
    $unanswered = ConfigurationPricer::price($listing, [], null, null, [], 1);

    expect($answered->lines)->toHaveCount(2)
        ->and($answered->lines[1]->amount->cents)->toBe(500)
        ->and($unanswered->lines)->toHaveCount(1);
});

it('prices a measurement answer on its rate', function (): void {
    $listing = $this->listing($this->seller());
    $modifier = Modifier::factory()->measurement('in', 0, 20, 150)->create(['listing_id' => $listing->id, 'prompt' => 'Engraved length']);

    $breakdown = ConfigurationPricer::price($listing, [], null, null, [$modifier->id => '4'], 1);

    expect($breakdown->lines[1]->amount->cents)->toBe(600);
});

it('skips a modifier out of scope even when an answer is given', function (): void {
    $listing = $this->listing($this->seller());
    $axis = OptionAxis::factory()->create(['listing_id' => $listing->id]);
    $blank = OptionValue::factory()->create(['axis_id' => $axis->id]);
    $personalized = OptionValue::factory()->create(['axis_id' => $axis->id]);
    $modifier = Modifier::factory()->create(['listing_id' => $listing->id, 'add_on_price_cents' => 300]);
    ModifierScope::factory()->create(['modifier_id' => $modifier->id, 'option_value_id' => $personalized->id]);

    $breakdown = ConfigurationPricer::price($listing, [$blank], null, null, [$modifier->id => 'hi'], 1);

    expect($breakdown->lines)->toHaveCount(1);
});

it('scales lines by quantity and applies the best tier discount', function (): void {
    $listing = $this->listing($this->seller(), ['price_cents' => 300]);
    QuantityBreak::factory()->create(['listing_id' => $listing->id, 'min_qty' => 50, 'discount_bps' => 500]);
    QuantityBreak::factory()->create(['listing_id' => $listing->id, 'min_qty' => 100, 'discount_bps' => 1000]);

    $breakdown = ConfigurationPricer::price($listing, [], null, null, [], 100);

    expect($breakdown->lines)->toHaveCount(2)
        ->and($breakdown->lines[0]->amount->cents)->toBe(30000)
        ->and($breakdown->lines[1]->label)->toBe('Quantity discount (100+)')
        ->and($breakdown->total()->cents)->toBe(27000);
});
