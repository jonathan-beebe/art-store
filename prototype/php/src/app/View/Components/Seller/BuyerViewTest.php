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

it('shows a standalone option’s own absolute price whether or not it is selected', function (): void {
    $listing = $this->listing($this->seller(), ['price_cents' => 1800]);
    $axis = OptionAxis::factory()->standalone()->create(['listing_id' => $listing->id, 'name' => 'Size']);
    OptionValue::factory()->priced(1800)->create(['axis_id' => $axis->id, 'label' => '8x10', 'is_default' => true]);
    OptionValue::factory()->priced(2400)->create(['axis_id' => $axis->id, 'label' => '11x14']);

    $html = Blade::render('<x-seller.buyer-view :listing="$listing" />', ['listing' => $listing]);

    expect($html)->toContain('8x10 ($18.00)')
        ->toContain('11x14 ($24.00)');
});

it('IMPRV-015: renders a live GET form that round-trips on the seller URL, never the cart route', function (): void {
    $listing = $this->listing($this->seller(), ['price_cents' => 1000]);
    $axis = OptionAxis::factory()->create(['listing_id' => $listing->id]);
    OptionValue::factory()->create(['axis_id' => $axis->id, 'is_default' => true]);

    $html = Blade::render('<x-seller.buyer-view :listing="$listing" />', ['listing' => $listing]);

    expect($html)->toContain('<form method="GET"')
        ->and($html)->not->toContain('cart/add')
        ->and($html)->toContain('aria-disabled="true" class="inline-block rounded-full bg-line px-6 py-2 text-sm font-medium text-ink-faint">Add to cart');
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

it('BUG-013: shows the title, price, and an inert add-to-cart presentation for an unconfigured listing', function (): void {
    $listing = $this->listing($this->seller(), ['title' => 'Winter Elm', 'price_cents' => 4100, 'quantity' => 5]);

    $html = Blade::render('<x-seller.buyer-view :listing="$listing" />', ['listing' => $listing]);

    expect($html)->toContain('Winter Elm')
        ->toContain('$41.00')
        ->toContain('5 in stock')
        ->toContain('Add to cart');
    expect($html)->not->toContain('<form');
    expect($html)->not->toContain('Nothing here yet for a buyer to configure');
});

it('BUG-013: shows the made-to-order label for an unconfigured listing with no fixed quantity', function (): void {
    $listing = $this->listing($this->seller(), ['quantity' => null]);

    $html = Blade::render('<x-seller.buyer-view :listing="$listing" />', ['listing' => $listing]);

    expect($html)->toContain('Made to order');
});

it('BUG-013: shows the zero-stock label for an unconfigured listing that has sold out', function (): void {
    $listing = $this->listing($this->seller(), ['quantity' => 0]);

    $html = Blade::render('<x-seller.buyer-view :listing="$listing" />', ['listing' => $listing]);

    expect($html)->toContain('0 in stock');
});

it('appends a caption to the panel badge when one is given', function (): void {
    $listing = $this->listing($this->seller());

    $html = Blade::render('<x-seller.buyer-view :listing="$listing" caption="Version: Blank" />', ['listing' => $listing]);

    expect($html)->toContain('What buyers see')->toContain('Version: Blank');
});

it('frames the panel in theme tokens so it flips with the shop partials it wraps in dark mode', function (): void {
    $listing = $this->listing($this->seller());

    $html = Blade::render('<x-seller.buyer-view :listing="$listing" />', ['listing' => $listing]);

    expect($html)->toContain('border-line-strong bg-surface')
        ->and($html)->toContain('bg-ink px-3 py-0.5 text-xs font-medium text-canvas')
        ->and($html)->not->toContain('bg-white')
        ->and($html)->not->toContain('text-neutral-900')
        ->and($html)->not->toContain('text-neutral-500')
        ->and($html)->not->toContain('border-neutral-400')
        ->and($html)->not->toContain('bg-neutral-800');
});

it('B1: carries the char limit as a maxlength, same as the shop page', function (): void {
    $listing = $this->listing($this->seller());
    Modifier::factory()->create(['listing_id' => $listing->id, 'prompt' => 'Name to letter', 'char_limit' => 20]);

    $html = Blade::render('<x-seller.buyer-view :listing="$listing" />', ['listing' => $listing]);

    expect($html)->toContain('maxlength="20"');
});

it('B2: shows the flat charge in the price breakdown once answered, same as the shop page', function (): void {
    // IMPRV-015: the panel no longer names the flat charge on the label
    // itself — the shop page never did either, so showing it there was
    // the panel claiming something a buyer never actually sees.
    $listing = $this->listing($this->seller(), ['price_cents' => 1400]);
    $modifier = Modifier::factory()->create(['listing_id' => $listing->id, 'prompt' => 'Name to letter', 'add_on_price_cents' => 200]);

    $unanswered = Blade::render('<x-seller.buyer-view :listing="$listing" />', ['listing' => $listing]);
    $answered = Blade::render(
        '<x-seller.buyer-view :listing="$listing" :input="$input" />',
        ['listing' => $listing, 'input' => ConfiguratorInput::of([], null, [$modifier->id => 'Wren'], 1)],
    );

    expect($unanswered)->toContain('Name to letter')
        ->and($unanswered)->not->toContain('$16.00')
        ->and($answered)->toContain('Name to letter')
        ->and($answered)->toContain('$16.00');
});

it('B7: carries the min, max, and unit on the buyer measurement input', function (): void {
    $listing = $this->listing($this->seller());
    Modifier::factory()->measurement('mm', 10.0, 100.0, 50)->create(['listing_id' => $listing->id, 'prompt' => 'Engraved length']);

    $html = Blade::render('<x-seller.buyer-view :listing="$listing" />', ['listing' => $listing]);

    expect($html)->toContain('min="10"')->toContain('max="100"')->toContain('mm');
});

it('C3: bolds the active quantity tier and totals the discounted breakdown', function (): void {
    // IMPRV-015: the tier table is the shared configurator partial's own
    // <table> now (identical to the shop page), not a panel-only <ul>.
    $listing = $this->listing($this->seller(), ['price_cents' => 450]);
    QuantityBreak::factory()->create(['listing_id' => $listing->id, 'min_qty' => 50, 'discount_bps' => 1000]);
    QuantityBreak::factory()->create(['listing_id' => $listing->id, 'min_qty' => 200, 'discount_bps' => 2200]);

    $html = Blade::render(
        '<x-seller.buyer-view :listing="$listing" :input="$input" />',
        ['listing' => $listing, 'input' => ConfiguratorInput::of([], null, [], 200)],
    );

    expect($html)->toContain('200+')
        ->and($html)->toContain('22% off')
        ->and($html)->toContain('<tr class="font-semibold">')
        ->and($html)->toContain('$702.00');
});

it('B6: shows a scoped question only for the selection it applies to', function (): void {
    $listing = $this->listing($this->seller());
    $axis = OptionAxis::factory()->create(['listing_id' => $listing->id, 'name' => 'Version']);
    $lettered = OptionValue::factory()->create(['axis_id' => $axis->id, 'label' => 'Hand-lettered']);
    $blank = OptionValue::factory()->create(['axis_id' => $axis->id, 'label' => 'Blank']);
    $modifier = Modifier::factory()->create(['listing_id' => $listing->id, 'prompt' => 'What name should we letter?']);
    $modifier->scopes()->create(['seller_id' => $modifier->seller_id, 'option_value_id' => $lettered->id]);

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
