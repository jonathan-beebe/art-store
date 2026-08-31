<?php

declare(strict_types=1);

use App\Actions\Configurator\AddOptionValue;
use App\Actions\Configurator\CreateOptionAxis;
use App\Actions\Configurator\GenerateVariants;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;

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
    $listing = $this->listing($this->seller('The Burrow Craftworks'), ['title' => 'Burrow Kitchen Tea Bowl']);
    $this->mediumAttribute($listing, 'Ceramic');

    $response = $this->get('/design-system');

    $response->assertOk();
    $response->assertSee('Burrow Kitchen Tea Bowl');
    $response->assertSee('The Burrow Craftworks');
    $response->assertSee('Ceramic');
    $response->assertDontSee('No for-sale listing yet');
    $response->assertDontSee('No attributed medium yet');
});

it('finds the configurable specimen listing with one query, not one probe per candidate', function (): void {
    $seller = $this->seller();
    $this->listing($seller, ['title' => 'Plain One']);
    $this->listing($seller, ['title' => 'Plain Two']);
    $configurable = $this->listing($seller, ['title' => 'Goblin-Wrought Ring']);
    $metal = app(CreateOptionAxis::class)($configurable, 'Metal');
    app(AddOptionValue::class)($metal, 'Gold', 0, isDefault: true);
    app(GenerateVariants::class)($configurable);

    // A standalone `->exists()` probe compiles to `select exists(select *
    // from ... ) as exists` — the shape the old per-listing PHP loop fired
    // once per candidate per table. `whereHas()` embeds the same EXISTS
    // clause inside the listings query itself, so the fixed lookup produces
    // none of these.
    $standaloneExistsProbes = 0;
    DB::listen(function (QueryExecuted $query) use (&$standaloneExistsProbes): void {
        $standaloneExistsProbes += str_starts_with($query->sql, 'select exists(') ? 1 : 0;
    });

    $response = $this->get('/design-system');

    $response->assertOk();
    $response->assertSee('Goblin-Wrought Ring');
    expect($standaloneExistsProbes)->toBe(0);
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
    $listing = $this->listing($this->seller(), ['title' => 'Goblin-Wrought Ring', 'price_cents' => 12000]);
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
