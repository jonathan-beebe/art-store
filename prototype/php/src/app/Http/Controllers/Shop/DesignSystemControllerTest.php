<?php

declare(strict_types=1);

use App\Actions\Configurator\AddOptionValue;
use App\Actions\Configurator\CreateOptionAxis;
use App\Actions\Configurator\GenerateVariants;

it('renders the token registry, pairings, and specimen notes on an empty catalog', function (): void {
    $response = $this->get('/design-system');

    $response->assertOk();
    $response->assertSee('Design system');
    $response->assertSee('Theme: Warm Craft');
    $response->assertSee('canvas');
    $response->assertSee('#f6efe4 · #221a14');
    $response->assertSee('ink-muted');
    $response->assertSee('Young Serif');
    $response->assertSee('No for-sale listing yet');
    $response->assertSee('No attributed medium yet');
    $response->assertSee('No configurable for-sale listing yet');
    // The Mobile section's phone frames point at the specimen routes.
    $response->assertSee('/design-system/specimens/browse-sheet', escape: false);
    $response->assertSee('/design-system/specimens/buy-bar', escape: false);
});

it('rates every promised pairing AA in both modes', function (): void {
    $response = $this->get('/design-system');

    $response->assertOk();
    $response->assertDontSee('bg-danger-surface text-danger">light');
    $response->assertDontSee('bg-danger-surface text-danger">dark');
});

it('renders real listings and the browse row once the catalog has them', function (): void {
    $listing = $this->listing($this->seller('Red Clay Works'), ['title' => 'Thrown stoneware vase']);
    $this->mediumAttribute($listing, 'Ceramic');

    $response = $this->get('/design-system');

    $response->assertOk();
    $response->assertSee('Thrown stoneware vase');
    $response->assertSee('Red Clay Works');
    $response->assertSee('Ceramic');
    $response->assertDontSee('No for-sale listing yet');
    $response->assertDontSee('No attributed medium yet');
});

it('renders the category-picker explorations with live counts and covers', function (): void {
    $seller = $this->seller();
    $ceramic = $this->listing($seller);
    $oil = $this->listing($seller);
    $this->mediumAttribute($ceramic, 'Ceramic');
    $this->mediumAttribute($oil, 'Oil');
    $this->listingImage($ceramic);

    $response = $this->get('/design-system');

    $response->assertOk();
    $response->assertSee('Category pickers');
    // The gallery panel totals the catalog across both media.
    $response->assertSee('2 works');
    // The photo variants wear the medium's real cover image.
    $response->assertSee("background-image: url('".$ceramic->imageUrl()."')", escape: false);
    $response->assertSee('All media');
    $response->assertSee('Browse media');
});

it('renders the configurator specimen live: selections reprice on this page', function (): void {
    $listing = $this->listing($this->seller(), ['title' => 'Custom ring', 'price_cents' => 12000]);
    $metal = app(CreateOptionAxis::class)($listing, 'Metal');
    app(AddOptionValue::class)($metal, 'Gold', 0, isDefault: true);
    $roseGold = app(AddOptionValue::class)($metal, 'Rose Gold', 800);
    app(GenerateVariants::class)($listing);

    $default = $this->get('/design-system');

    $default->assertOk();
    $default->assertSee('Metal');
    $default->assertSee('$120.00');

    $repriced = $this->get('/design-system?'.http_build_query([
        'axis' => [$metal->id => $roseGold->id],
        'focus' => 'axis-'.$metal->id,
    ]));

    $repriced->assertOk();
    $repriced->assertSee('$128.00');
});
