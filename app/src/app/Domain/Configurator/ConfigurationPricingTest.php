<?php

declare(strict_types=1);

use App\Domain\Configurator\ConfigurationPricing;
use App\Domain\Configurator\ModifierKind;
use App\Domain\Configurator\PricedModifier;
use App\Domain\Configurator\PricedModifierOption;
use App\Domain\Configurator\PricedOption;
use App\Domain\Configurator\PricingConfiguration;
use App\Domain\Configurator\QuantityDiscount;
use App\Domain\Money\Money;

$base = Money::fromCents(2000);
$addOn = fn (string $id, string $label, int $surchargeCents): PricedOption => PricedOption::of($id, 'Frame', $label, false, Money::zero(), Money::fromCents($surchargeCents));
$standalone = fn (string $id, string $label, int $priceCents): PricedOption => PricedOption::of($id, 'Size', $label, true, Money::fromCents($priceCents), Money::zero());

it('prices the base alone with no selection', function () use ($base): void {
    $breakdown = ConfigurationPricing::price(PricingConfiguration::of($base, false, [], null, [], [], []), 1);

    expect($breakdown->lines)->toHaveCount(1)
        ->and($breakdown->lines[0]->label)->toBe('Base price')
        ->and($breakdown->lines[0]->signed)->toBeFalse()
        ->and($breakdown->total())->toBeMoney(2000);
});

it('adds a signed line for each add-on value that surcharges and none for a free one', function () use ($base, $addOn): void {
    $priced = ConfigurationPricing::price(PricingConfiguration::of($base, false, [$addOn('ovl_gold', 'Rose Gold', 800)], null, [], [], []), 1);
    $free = ConfigurationPricing::price(PricingConfiguration::of($base, false, [$addOn('ovl_free', 'Gold', 0)], null, [], [], []), 1);

    expect($priced->lines)->toHaveCount(2)
        ->and($priced->lines[1]->label)->toBe('Rose Gold')
        ->and($priced->lines[1]->signed)->toBeTrue()
        ->and($priced->total())->toBeMoney(2800)
        ->and($free->lines)->toHaveCount(1);
});

it('replaces every per-unit line with the override, labeled by the selected combination', function () use ($base, $addOn): void {
    $selected = [$addOn('ovl_l', '48 in', 500), $addOn('ovl_w', '30 in', 0)];
    $breakdown = ConfigurationPricing::price(PricingConfiguration::of($base, false, $selected, Money::fromCents(110000), [], [], []), 1);

    expect($breakdown->lines)->toHaveCount(1)
        ->and($breakdown->lines[0]->label)->toBe('48 in / 30 in')
        ->and($breakdown->lines[0]->signed)->toBeFalse()
        ->and($breakdown->total())->toBeMoney(110000);
});

it('itemizes every selection once a standalone axis exists, the add-on ones signed even at zero', function () use ($base, $addOn, $standalone): void {
    $selected = [$standalone('ovl_8x10', '8 × 10', 4500), $addOn('ovl_unframed', 'Unframed', 0)];
    $breakdown = ConfigurationPricing::price(PricingConfiguration::of($base, true, $selected, null, [], [], []), 1);

    expect(array_map(fn ($line): string => $line->label, $breakdown->lines))->toBe(['Size: 8 × 10', 'Frame: Unframed'])
        ->and($breakdown->lines[0]->signed)->toBeFalse()
        ->and($breakdown->lines[1]->signed)->toBeTrue()
        ->and($breakdown->total())->toBeMoney(4500);
});

it('adds an answered modifier that applies and skips one out of scope, unanswered, or free', function () use ($base, $addOn): void {
    $engraving = PricedModifier::of('mdf_text', 'Engraving', ModifierKind::Text, Money::fromCents(400), null, [], []);
    $font = PricedModifier::of('mdf_font', 'Font', ModifierKind::Select, Money::zero(), null, [PricedModifierOption::of('mop_script', 'Script', Money::fromCents(300))], ['ovl_personalized']);
    $note = PricedModifier::of('mdf_note', 'Gift note', ModifierKind::Text, Money::zero(), null, [], []);
    $answers = ['mdf_text' => 'Congrats!', 'mdf_font' => 'mop_script', 'mdf_note' => 'Enjoy'];

    $breakdown = ConfigurationPricing::price(PricingConfiguration::of($base, false, [$addOn('ovl_plain', 'Plain', 0)], null, [$engraving, $font, $note], $answers, []), 1);

    expect(array_map(fn ($line): string => $line->label, $breakdown->lines))->toBe(['Base price', 'Engraving'])
        ->and($breakdown->total())->toBeMoney(2400);
});

it('scales every line by quantity and applies the best tier', function () use ($base): void {
    $tiers = [QuantityDiscount::of(10, 1000), QuantityDiscount::of(100, 2000)];
    $breakdown = ConfigurationPricing::price(PricingConfiguration::of($base, false, [], null, [], [], $tiers), 100);

    expect($breakdown->lines[0]->amount)->toBeMoney(200000)
        ->and($breakdown->total())->toBeMoney(160000);
});
