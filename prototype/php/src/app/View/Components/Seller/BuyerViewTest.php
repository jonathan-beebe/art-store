<?php

declare(strict_types=1);

namespace App\View\Components\Seller;

use App\Models\OptionAxis;
use App\Models\OptionValue;
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

it('renders an unconfigured listing with a plain notice instead of controls', function (): void {
    $listing = $this->listing($this->seller());

    $html = Blade::render('<x-seller.buyer-view :listing="$listing" />', ['listing' => $listing]);

    expect($html)->toContain('Nothing here yet for a buyer to configure');
});
