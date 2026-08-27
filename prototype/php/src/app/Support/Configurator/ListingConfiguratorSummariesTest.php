<?php

declare(strict_types=1);

namespace App\Support\Configurator;

use App\Domain\Configurator\PricingMode;
use App\Domain\Configurator\UnitState;
use App\Models\DescriptionSection;
use App\Models\Modifier;
use App\Models\ModifierOption;
use App\Models\ModifierScope;
use App\Models\OptionAxis;
use App\Models\OptionValue;
use App\Models\QuantityBreak;
use App\Models\Unit;
use App\Models\Variant;

it('reports zero images and no urls for a listing with none', function (): void {
    $listing = $this->listing($this->seller());

    expect(ListingConfiguratorSummaries::images($listing))->toBe(['urls' => [], 'count' => 0]);
});

it('lists images cover-first by position', function (): void {
    $listing = $this->listing($this->seller());
    $second = $this->listingImage($listing, ['path' => 'listings/second.jpg', 'position' => 1]);
    $first = $this->listingImage($listing, ['path' => 'listings/first.jpg', 'position' => 0]);

    $images = ListingConfiguratorSummaries::images($listing);

    expect($images['urls'])->toBe([$first->url(), $second->url()])
        ->and($images['count'])->toBe(2);
});

it('has no choices summary for a listing with no choices', function (): void {
    $listing = $this->listing($this->seller());

    expect(ListingConfiguratorSummaries::choices($listing))->toBeNull();
});

it('summarizes a choice, its priced option, and its combination coverage', function (): void {
    $listing = $this->listing($this->seller());
    $axis = OptionAxis::factory()->create(['listing_id' => $listing->id, 'name' => 'Size']);
    $small = OptionValue::factory()->create(['axis_id' => $axis->id, 'label' => '11 oz', 'is_default' => true, 'position' => 0]);
    $large = OptionValue::factory()->create(['axis_id' => $axis->id, 'label' => '15 oz', 'surcharge_cents' => 400, 'position' => 1]);

    $enabled = Variant::factory()->create(['listing_id' => $listing->id, 'combo_key' => 'a', 'enabled' => true]);
    $enabled->options()->create(['axis_id' => $axis->id, 'option_value_id' => $large->id]);
    $disabled = Variant::factory()->create(['listing_id' => $listing->id, 'combo_key' => 'b', 'enabled' => false]);
    $disabled->options()->create(['axis_id' => $axis->id, 'option_value_id' => $small->id]);

    /** @var array{axes: list<array{name: string, displayedLabels: list<string>, priceDeltas: list<string>, moreCount: int}>, offeredCount: int, totalCombinations: int, lowStockCount: int, combinationsUrl: string} $summary */
    $summary = ListingConfiguratorSummaries::choices($listing);

    expect($summary)->not->toBeNull()
        ->and($summary['axes'])->toHaveCount(1)
        ->and($summary['axes'][0]['name'])->toBe('Size')
        ->and($summary['axes'][0]['displayedLabels'])->toBe(['11 oz', '15 oz'])
        ->and($summary['axes'][0]['priceDeltas'])->toBe(['+$4.00'])
        ->and($summary['axes'][0]['moreCount'])->toBe(0)
        ->and($summary['offeredCount'])->toBe(1)
        ->and($summary['totalCombinations'])->toBe(2)
        ->and($summary['combinationsUrl'])->toBe(route('seller.listings.variants.index', $listing));
});

it('summarizes a standalone choice with each displayed option’s own absolute price, unfiltered and unsigned', function (): void {
    $listing = $this->listing($this->seller());
    $axis = OptionAxis::factory()->standalone()->create(['listing_id' => $listing->id, 'name' => 'Size']);
    OptionValue::factory()->priced(1800)->create(['axis_id' => $axis->id, 'label' => '8x10', 'position' => 0]);
    OptionValue::factory()->priced(2400)->create(['axis_id' => $axis->id, 'label' => '11x14', 'position' => 1]);
    OptionValue::factory()->priced(3400)->create(['axis_id' => $axis->id, 'label' => '16x20', 'position' => 2]);

    /** @var array{axes: list<array{name: string, pricingMode: PricingMode, displayedLabels: list<string>, priceDeltas: list<string>, moreCount: int}>, offeredCount: int, totalCombinations: int, lowStockCount: int, combinationsUrl: string} $summary */
    $summary = ListingConfiguratorSummaries::choices($listing);

    expect($summary['axes'][0]['pricingMode'])->toBe(PricingMode::Standalone)
        ->and($summary['axes'][0]['displayedLabels'])->toBe(['8x10', '11x14', '16x20'])
        ->and($summary['axes'][0]['priceDeltas'])->toBe(['$18.00', '$24.00', '$34.00']);
});

it('names an add-on choice’s pricing mode alongside its filtered, signed price deltas', function (): void {
    $listing = $this->listing($this->seller());
    $axis = OptionAxis::factory()->addOn()->create(['listing_id' => $listing->id, 'name' => 'Frame']);
    OptionValue::factory()->create(['axis_id' => $axis->id, 'label' => 'Unframed', 'surcharge_cents' => 0, 'position' => 0]);
    OptionValue::factory()->create(['axis_id' => $axis->id, 'label' => 'Black frame', 'surcharge_cents' => 3200, 'position' => 1]);

    /** @var array{axes: list<array{name: string, pricingMode: PricingMode, displayedLabels: list<string>, priceDeltas: list<string>, moreCount: int}>, offeredCount: int, totalCombinations: int, lowStockCount: int, combinationsUrl: string} $summary */
    $summary = ListingConfiguratorSummaries::choices($listing);

    expect($summary['axes'][0]['pricingMode'])->toBe(PricingMode::AddOn)
        ->and($summary['axes'][0]['priceDeltas'])->toBe(['+$32.00']);
});

it('collapses a choice past three options into a more count', function (): void {
    $listing = $this->listing($this->seller());
    $axis = OptionAxis::factory()->create(['listing_id' => $listing->id, 'name' => 'Color']);
    foreach (['Cream', 'Butter', 'Sage', 'Rust', 'Moss'] as $position => $label) {
        OptionValue::factory()->create(['axis_id' => $axis->id, 'label' => $label, 'position' => $position]);
    }

    /** @var array{axes: list<array{name: string, displayedLabels: list<string>, priceDeltas: list<string>, moreCount: int}>, offeredCount: int, totalCombinations: int, lowStockCount: int, combinationsUrl: string} $summary */
    $summary = ListingConfiguratorSummaries::choices($listing);

    expect($summary['axes'][0]['displayedLabels'])->toBe(['Cream', 'Butter', 'Sage'])
        ->and($summary['axes'][0]['moreCount'])->toBe(2);
});

it('counts a low quantity enabled non-serialized variant as low on stock', function (): void {
    $listing = $this->listing($this->seller());
    $axis = OptionAxis::factory()->create(['listing_id' => $listing->id]);
    OptionValue::factory()->create(['axis_id' => $axis->id]);
    Variant::factory()->create(['listing_id' => $listing->id, 'combo_key' => 'low', 'enabled' => true, 'quantity' => 2]);
    Variant::factory()->create(['listing_id' => $listing->id, 'combo_key' => 'plenty', 'enabled' => true, 'quantity' => 50]);
    Variant::factory()->create(['listing_id' => $listing->id, 'combo_key' => 'serial', 'enabled' => true, 'is_serialized' => true, 'quantity' => null]);

    /** @var array{axes: list<array{name: string, displayedLabels: list<string>, priceDeltas: list<string>, moreCount: int}>, offeredCount: int, totalCombinations: int, lowStockCount: int, combinationsUrl: string} $summary */
    $summary = ListingConfiguratorSummaries::choices($listing);

    expect($summary['lowStockCount'])->toBe(1);
});

it('has no questions summary for a listing with no questions', function (): void {
    $listing = $this->listing($this->seller());

    expect(ListingConfiguratorSummaries::questions($listing))->toBeNull();
});

it('summarizes a required, priced, scoped question', function (): void {
    $listing = $this->listing($this->seller());
    $axis = OptionAxis::factory()->create(['listing_id' => $listing->id, 'name' => 'Version']);
    $handLettered = OptionValue::factory()->create(['axis_id' => $axis->id, 'label' => 'Hand-lettered']);
    $modifier = Modifier::factory()->required()->create([
        'listing_id' => $listing->id,
        'prompt' => 'What name should we letter?',
        'add_on_price_cents' => 200,
    ]);
    ModifierScope::factory()->create(['modifier_id' => $modifier->id, 'option_value_id' => $handLettered->id]);

    /** @var list<array{prompt: string, priceLabel: ?string, required: bool, scopeNote: ?string}> $summary */
    $summary = ListingConfiguratorSummaries::questions($listing);

    expect($summary)->toHaveCount(1)
        ->and($summary[0]['prompt'])->toBe('What name should we letter?')
        ->and($summary[0]['priceLabel'])->toBe('+$2.00')
        ->and($summary[0]['required'])->toBeTrue()
        ->and($summary[0]['scopeNote'])->toBe('only asked when Version is Hand-lettered');
});

it('summarizes an unscoped, unpriced, optional question', function (): void {
    $listing = $this->listing($this->seller());
    Modifier::factory()->create(['listing_id' => $listing->id, 'prompt' => 'Any notes?']);

    /** @var list<array{prompt: string, priceLabel: ?string, required: bool, scopeNote: ?string}> $summary */
    $summary = ListingConfiguratorSummaries::questions($listing);

    expect($summary[0]['priceLabel'])->toBeNull()
        ->and($summary[0]['required'])->toBeFalse()
        ->and($summary[0]['scopeNote'])->toBeNull();
});

it('prices a select question from its priciest option', function (): void {
    $listing = $this->listing($this->seller());
    $modifier = Modifier::factory()->select()->create(['listing_id' => $listing->id, 'prompt' => 'Font']);
    ModifierOption::factory()->create(['modifier_id' => $modifier->id, 'add_on_price_cents' => 0]);
    ModifierOption::factory()->create(['modifier_id' => $modifier->id, 'add_on_price_cents' => 150]);

    /** @var list<array{prompt: string, priceLabel: ?string, required: bool, scopeNote: ?string}> $summary */
    $summary = ListingConfiguratorSummaries::questions($listing);

    expect($summary[0]['priceLabel'])->toBe('+$1.50');
});

it('has no discounts line for a listing with no quantity breaks', function (): void {
    $listing = $this->listing($this->seller());

    expect(ListingConfiguratorSummaries::discountsLine($listing))->toBeNull();
});

it('joins quantity discount tiers into one line', function (): void {
    $listing = $this->listing($this->seller());
    QuantityBreak::factory()->create(['listing_id' => $listing->id, 'min_qty' => 12, 'discount_bps' => 1500]);
    QuantityBreak::factory()->create(['listing_id' => $listing->id, 'min_qty' => 6, 'discount_bps' => 1000]);

    expect(ListingConfiguratorSummaries::discountsLine($listing))->toBe('6 or more — 10% off each · 12 or more — 15% off each');
});

it('has no sections line for a listing with no page sections', function (): void {
    $listing = $this->listing($this->seller());

    expect(ListingConfiguratorSummaries::sectionsLine($listing))->toBeNull();
});

it('joins page section titles into one line', function (): void {
    $listing = $this->listing($this->seller());
    DescriptionSection::factory()->create(['listing_id' => $listing->id, 'position' => 1, 'title' => 'Care']);
    DescriptionSection::factory()->create(['listing_id' => $listing->id, 'position' => 0, 'title' => 'How to order']);

    expect(ListingConfiguratorSummaries::sectionsLine($listing))->toBe('How to order · Care');
});

it('has no pieces summary for a listing with no serialized combination', function (): void {
    $listing = $this->listing($this->seller());
    Variant::factory()->create(['listing_id' => $listing->id, 'combo_key' => 'a']);

    expect(ListingConfiguratorSummaries::pieces($listing))->toBeNull();
});

it('summarizes available and sold piece counts for a serialized combination', function (): void {
    $listing = $this->listing($this->seller());
    $variant = Variant::factory()->serialized()->create(['listing_id' => $listing->id, 'combo_key' => 'a']);
    Unit::factory()->create(['variant_id' => $variant->id, 'state' => UnitState::Available]);
    Unit::factory()->create(['variant_id' => $variant->id, 'state' => UnitState::Available]);
    Unit::factory()->create(['variant_id' => $variant->id, 'state' => UnitState::Sold]);

    $summary = ListingConfiguratorSummaries::pieces($listing);

    expect($summary)->toBe([
        'total' => 3,
        'available' => 2,
        'sold' => 1,
        'url' => route('seller.listings.variants.index', $listing),
    ]);
});
