<?php

declare(strict_types=1);

namespace App\View\Components\Seller;

use App\Actions\Configurator\CreateVariant;
use App\Models\Modifier;
use App\Models\OptionAxis;
use App\Models\OptionValue;
use App\Models\QuantityBreak;
use App\Support\Configurator\ConfiguratorInput;
use Illuminate\Support\Facades\Blade;

it('renders a priced options price difference and the breakdown total', function (): void {
    $listing = $this->listing($this->seller(), ['price_cents' => 3500]);
    $axis = OptionAxis::factory()->create(['listing_id' => $listing->id, 'name' => 'Size']);
    OptionValue::factory()->create(['axis_id' => $axis->id, 'label' => 'Large', 'surcharge_cents' => 600, 'is_default' => true]);

    $html = Blade::render('<x-seller.buyer-view :listing="$listing" />', ['listing' => $listing]);

    expect($html)->toContain('What buyers see')
        ->toContain('Large')
        ->toContain('+$6.00')
        ->toContain('$41.00');
});

it('renders no live form and no submit action for a shop route', function (): void {
    $listing = $this->listing($this->seller(), ['price_cents' => 1000]);
    $axis = OptionAxis::factory()->create(['listing_id' => $listing->id]);
    OptionValue::factory()->create(['axis_id' => $axis->id, 'is_default' => true]);

    $html = Blade::render('<x-seller.buyer-view :listing="$listing" />', ['listing' => $listing]);

    expect($html)->not->toContain('<form');
    expect($html)->not->toContain('cart/add');
});

it('greys out an option no combination offers, with its reason', function (): void {
    $listing = $this->listing($this->seller());
    $axis = OptionAxis::factory()->create(['listing_id' => $listing->id, 'name' => 'Color']);
    $red = OptionValue::factory()->create(['axis_id' => $axis->id, 'label' => 'Red', 'is_default' => true]);
    $blue = OptionValue::factory()->create(['axis_id' => $axis->id, 'label' => 'Blue']);
    app(CreateVariant::class)($listing, [$red]);

    $html = Blade::render('<x-seller.buyer-view :listing="$listing" />', ['listing' => $listing]);

    expect($html)->toContain('Blue')
        ->toContain('not offered')
        ->toContain('disabled');
});

it('renders an unconfigured listing with a plain notice instead of controls', function (): void {
    $listing = $this->listing($this->seller());

    $html = Blade::render('<x-seller.buyer-view :listing="$listing" />', ['listing' => $listing]);

    expect($html)->toContain('Nothing here yet for a buyer to configure');
});

it('appends a caption to the panel badge when one is given', function (): void {
    $listing = $this->listing($this->seller());

    $html = Blade::render('<x-seller.buyer-view :listing="$listing" caption="Version: Blank" />', ['listing' => $listing]);

    expect($html)->toContain('What buyers see')->toContain('Version: Blank');
});

it('B1: carries the char limit as a maxlength and names it under the field', function (): void {
    $listing = $this->listing($this->seller());
    Modifier::factory()->create(['listing_id' => $listing->id, 'prompt' => 'Name to letter', 'char_limit' => 20]);

    $html = Blade::render('<x-seller.buyer-view :listing="$listing" />', ['listing' => $listing]);

    expect($html)->toContain('maxlength="20"')->toContain('Up to 20 letters.');
});

it('B2: shows the extra charge on the label and, once answered, in the price breakdown', function (): void {
    $listing = $this->listing($this->seller(), ['price_cents' => 1400]);
    $modifier = Modifier::factory()->create(['listing_id' => $listing->id, 'prompt' => 'Name to letter', 'add_on_price_cents' => 200]);

    $unanswered = Blade::render('<x-seller.buyer-view :listing="$listing" />', ['listing' => $listing]);
    $answered = Blade::render(
        '<x-seller.buyer-view :listing="$listing" :input="$input" />',
        ['listing' => $listing, 'input' => ConfiguratorInput::of([], null, [$modifier->id => 'Wren'], 1)],
    );

    expect($unanswered)->toContain('Name to letter')
        ->and($unanswered)->toContain('(+$2.00)')
        ->and($unanswered)->not->toContain('$16.00')
        ->and($answered)->toContain('Name to letter')
        ->and($answered)->toContain('(+$2.00)')
        ->and($answered)->toContain('$16.00');
});

it('B7: carries the min, max, and unit on the buyer measurement input', function (): void {
    $listing = $this->listing($this->seller());
    Modifier::factory()->measurement('mm', 10.0, 100.0, 50)->create(['listing_id' => $listing->id, 'prompt' => 'Engraved length']);

    $html = Blade::render('<x-seller.buyer-view :listing="$listing" />', ['listing' => $listing]);

    expect($html)->toContain('min="10"')->toContain('max="100"')->toContain('mm');
});

it('C3: bolds the active quantity tier and totals the discounted breakdown', function (): void {
    $listing = $this->listing($this->seller(), ['price_cents' => 450]);
    QuantityBreak::factory()->create(['listing_id' => $listing->id, 'min_qty' => 50, 'discount_bps' => 1000]);
    QuantityBreak::factory()->create(['listing_id' => $listing->id, 'min_qty' => 200, 'discount_bps' => 2200]);

    $html = Blade::render(
        '<x-seller.buyer-view :listing="$listing" :input="$input" />',
        ['listing' => $listing, 'input' => ConfiguratorInput::of([], null, [], 200)],
    );

    expect($html)->toContain('200+: 22% off')
        ->and($html)->toContain('font-semibold text-neutral-900')
        ->and($html)->toContain('$702.00');
});

it('B6: shows a scoped question only for the selection it applies to', function (): void {
    $listing = $this->listing($this->seller());
    $axis = OptionAxis::factory()->create(['listing_id' => $listing->id, 'name' => 'Version']);
    $lettered = OptionValue::factory()->create(['axis_id' => $axis->id, 'label' => 'Hand-lettered']);
    $blank = OptionValue::factory()->create(['axis_id' => $axis->id, 'label' => 'Blank']);
    $modifier = Modifier::factory()->create(['listing_id' => $listing->id, 'prompt' => 'What name should we letter?']);
    $modifier->scopes()->create(['option_value_id' => $lettered->id]);

    $applies = Blade::render(
        '<x-seller.buyer-view :listing="$listing" :input="$input" />',
        ['listing' => $listing, 'input' => ConfiguratorInput::of([$axis->id => $lettered->id], null, [], 1)],
    );
    $other = Blade::render(
        '<x-seller.buyer-view :listing="$listing" :input="$input" />',
        ['listing' => $listing, 'input' => ConfiguratorInput::of([$axis->id => $blank->id], null, [], 1)],
    );

    expect($applies)->toContain('What name should we letter?');
    expect($other)->not->toContain('What name should we letter?');
});
