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

it('documents the home page as a third layout shape and names the browse-shape variants correctly', function (): void {
    $response = $this->get('/design-system');

    $response->assertOk();
    // The home page's own anatomy.
    $response->assertSee('Home — storefront root');
    $response->assertSee('Featured band');
    $response->assertSee('Just listed');
    $response->assertSee('More to explore');
    $response->assertSee('wayfinding footer');
    // listing-grid.blade.php never renders 1-up.
    $response->assertSee('2-up → 3-up as the viewport grows', false);
    $response->assertDontSee('1-up → 2-up → 3-up');
    // The browse wireframe now names /medium and covers /browse's shape.
    $response->assertSee('Medium — browse');
    $response->assertSee('/browse/{categoryPath}', false);
});

it('presents the browse sheet as the shipped mobile pattern and the rest as explorations', function (): void {
    $response = $this->get('/design-system');

    $response->assertOk();
    $response->assertSee('the shipped pattern', false);
    $response->assertSee('an exploration the product does not currently wear', false);
    $response->assertDontSee('sticky buy bar, swipe galleries');
});

it('rates on-photo on photo-scrim honestly, composited over a worst-case white photo', function (): void {
    $response = $this->get('/design-system');

    $response->assertOk();
    $response->assertSee('on-photo');
    $response->assertSee('photo-scrim');
    $response->assertSee('worst-case white photo', false);
    // Still meets AA in both modes, alongside every other rated pairing.
    $response->assertDontSee('bg-danger-surface text-danger">light');
    $response->assertDontSee('bg-danger-surface text-danger">dark');
});

it('renders the card fields component live, with checkout\'s fake-card guidance', function (): void {
    $response = $this->get('/design-system');

    $response->assertOk();
    $response->assertSee('Card number');
    $response->assertSee('4242 4242 4242 4242');
});

it('notes no order-item-detail specimen exists until a listing resolves a variant', function (): void {
    $response = $this->get('/design-system');

    $response->assertOk();
    $response->assertSee('No configured for-sale listing yet');
});

it('renders a real order line for order-item-detail from the configurator specimen listing', function (): void {
    $listing = $this->listing($this->seller(), ['title' => 'Goblin-Wrought Ring', 'price_cents' => 12000]);
    $metal = app(CreateOptionAxis::class)($listing, 'Metal');
    app(AddOptionValue::class)($metal, 'Gold', 0, isDefault: true);
    app(GenerateVariants::class)($listing);

    $response = $this->get('/design-system');

    $response->assertOk();
    $response->assertSee('Order item detail');
    $response->assertSee('Metal:', false);
    $response->assertSee('Gold');
    $response->assertDontSee('No configured for-sale listing yet');
});
