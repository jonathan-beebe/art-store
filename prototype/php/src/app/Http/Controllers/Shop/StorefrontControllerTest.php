<?php

declare(strict_types=1);

use App\Domain\Listings\ListingStatus;
use App\Models\Category;
use App\Models\ListingRemoval;

it('shows a for sale listing with its artist and price', function (): void {
    $seller = $this->seller('Blue Kiln Studio');
    $this->listing($seller, ['title' => 'Harbour at Dawn', 'price_cents' => 24500]);

    $response = $this->get('/');

    $response->assertOk();
    $response->assertSee('Harbour at Dawn');
    $response->assertSee('Blue Kiln Studio');
    $response->assertSee('$245.00');
});

it('shows the listings cover image on its shop card', function (): void {
    $seller = $this->seller();
    $listing = $this->listing($seller, ['title' => 'Harbour at Dawn']);
    $this->listingImage($listing, ['path' => 'listings/cover.jpg', 'position' => 0]);

    $response = $this->get('/');

    $response->assertSee($listing->imageUrl(), escape: false);
});

it('leaves out listings that are not for sale', function (): void {
    $seller = $this->seller();
    $this->listing($seller, ['title' => 'Unfinished Sketch', 'status' => ListingStatus::Draft]);
    $this->listing($seller, ['title' => 'Sold Vase', 'status' => ListingStatus::Sold, 'quantity' => 0]);

    $response = $this->get('/');

    $response->assertDontSee('Unfinished Sketch');
    $response->assertDontSee('Sold Vase');
});

it('leaves out a removed listing even while its status still says for sale', function (): void {
    $seller = $this->seller();
    $listing = $this->listing($seller, ['title' => 'Recalled Print']);
    ListingRemoval::factory()->create(['listing_id' => $listing->id]);

    $response = $this->get('/');

    $response->assertDontSee('Recalled Print');
});

it('shows the three newest for-sale listings under Just listed and the next nine under More to explore, leaving the rest out', function (): void {
    $seller = $this->seller();
    for ($index = 1; $index <= 13; $index++) {
        $this->listing($seller, ['title' => sprintf('Study No %02d', $index), 'created_at' => moment(sprintf('2026-08-01 00:%02d:00', $index))]);
    }

    $response = $this->get('/');

    $response->assertOk();
    // Newest first: 13 is the most recent of the thirteen, so it leads Just
    // listed; 12 down to 2 fill out the nine after it — twelve listings
    // shown in all. 1, the oldest, is the only one left off the page
    // entirely — the browse and search paths own the rest.
    $response->assertSeeInOrder(['Study No 13', 'Study No 12', 'Study No 11', 'Study No 10']);
    $response->assertSee('Study No 02');
    $response->assertDontSee('Study No 01');
});

it('offers the media of listings that are for sale', function (): void {
    $seller = $this->seller();
    $forSale = $this->listing($seller);
    $draft = $this->listing($seller, ['status' => ListingStatus::Draft]);
    $this->mediumAttribute($forSale, 'Ceramic');
    $this->mediumAttribute($draft, 'Linocut');

    $response = $this->get('/');

    $response->assertSee('/medium/ceramic', escape: false);
    $response->assertDontSee('/medium/linocut', escape: false);
});

it('links each medium tile straight to its /medium page, carrying no search term', function (): void {
    $seller = $this->seller();
    $listing = $this->listing($seller);
    $this->mediumAttribute($listing, 'Ceramic');

    $response = $this->get('/');

    $response->assertSee('href="'.route('shop.medium', ['medium' => 'ceramic']).'"', escape: false);
});

it('renders the medium drawer\'s revealed tiles into the same grid and the same tile size as the row', function (): void {
    $seller = $this->seller();
    foreach (['Ceramic', 'Painting', 'Printmaking', 'Textile', 'Wood', 'Metal', 'Glass'] as $medium) {
        $this->mediumAttribute($this->listing($seller), $medium);
    }

    $response = $this->get('/')->assertOk();

    // One golden-ratio tile shape, used by the row and the drawer alike.
    $response->assertSeeInOrder(['aspect-[1.618/1]', 'aspect-[1.618/1]'], escape: false);
    // One grid — same columns, same gap — for the row and the drawer's panel.
    $response->assertSeeInOrder(['grid grid-cols-3 gap-3 sm:grid-cols-6', 'grid grid-cols-3 gap-3 sm:grid-cols-6'], escape: false);
    // Alphabetically, Wood is the 7th of the seven media — past the row's
    // best-stocked five (all tied at one listing apiece), so only the
    // drawer carries it.
    $response->assertSee('Wood');
});

it('links the browsable root categories with their listing counts', function (): void {
    $jewelry = Category::factory()->create(['name' => 'Jewelry', 'path' => '/jewelry/']);
    Category::factory()->create(['name' => 'Hidden Room', 'path' => '/hidden-room/', 'browsable' => false]);
    $this->listing($this->seller(), ['category_id' => $jewelry->id]);

    $response = $this->get('/');

    $response->assertSee('href="'.route('shop.browse', ['categoryPath' => 'jewelry']).'"', escape: false);
    $response->assertDontSee('Hidden Room');
});

it('shows a category tile\'s photo cover when a for-sale listing supplies one, and degrades to a tint fill when none does', function (): void {
    $seller = $this->seller();
    $jewelry = Category::factory()->create(['name' => 'Jewelry', 'path' => '/jewelry/']);
    $empty = Category::factory()->create(['name' => 'Tableware', 'path' => '/tableware/']);
    $covered = $this->listing($seller, ['category_id' => $jewelry->id]);
    $this->listingImage($covered, ['path' => 'listings/ring.jpg', 'position' => 0]);

    $response = $this->get('/')->assertOk();

    $response->assertSee("background-image: url('".$covered->imageUrl()."')", escape: false);
    $response->assertSee('Tableware');
    // The empty category still renders a tile — a tint fill, never a broken
    // `background-image: url('')`.
    $response->assertDontSee("background-image: url('')", escape: false);
});

it('shows a flashed magic link in the debug alert', function (): void {
    $response = $this->withSession(['debug_magic_link' => 'http://localhost:8000/auth/magic/abc123'])->get('/');

    $response->assertSee('http://localhost:8000/auth/magic/abc123', escape: false);
});

it('hides the debug alert without a flashed magic link', function (): void {
    $response = $this->get('/');

    $response->assertDontSee('Debug magic link');
});

it('links the built stylesheet', function (): void {
    $response = $this->get('/');

    $response->assertSee('/build/assets/', escape: false);
});

it('redirects a legacy q to /search, keeping the term', function (): void {
    $response = $this->get('/?q=harbour');

    $response->assertRedirect('/search?q=harbour');
});

it('redirects a legacy medium with no q to /medium/{medium}', function (): void {
    $response = $this->get('/?medium=ceramic');

    $response->assertRedirect('/medium/ceramic');
});

it('redirects a legacy medium composed with q to /search, dropping the medium', function (): void {
    $response = $this->get('/?medium=ceramic&q=cup');

    $response->assertRedirect('/search?q=cup');
});

it('renders the configured featured listing in the featured band', function (): void {
    config(['storefront.featured' => ['type' => 'listing', 'value' => 'harbour-at-dawn']]);
    $listing = $this->listing($this->seller('Blue Kiln Studio'), [
        'title' => 'Harbour at Dawn',
        'slug' => 'harbour-at-dawn',
        'price_cents' => 24500,
    ]);

    $response = $this->get('/')->assertOk();

    $response->assertSee('Featured');
    $response->assertSee('Harbour at Dawn');
    $response->assertSee('$245.00');
    $response->assertSee('href="'.route('shop.listing', $listing).'"', escape: false);
});

it('renders the configured featured category in the featured band', function (): void {
    config(['storefront.featured' => ['type' => 'category', 'value' => 'jewelry']]);
    $jewelry = Category::factory()->create(['name' => 'Jewelry', 'path' => '/jewelry/']);
    $this->listing($this->seller(), ['category_id' => $jewelry->id]);

    $response = $this->get('/')->assertOk();

    $response->assertSee('Featured');
    $response->assertSee('Browse Jewelry');
    $response->assertSee('href="'.route('shop.browse', ['categoryPath' => 'jewelry']).'"', escape: false);
});

it('renders no featured band, without error, when the configured subject is missing', function (): void {
    config(['storefront.featured' => ['type' => 'listing', 'value' => 'nothing-by-this-slug']]);

    $response = $this->get('/');

    $response->assertOk();
    $response->assertDontSee('Featured');
});

it('renders no featured band, without error, when the configured listing is no longer for sale', function (): void {
    config(['storefront.featured' => ['type' => 'listing', 'value' => 'sold-vase']]);
    $this->listing($this->seller(), ['title' => 'Sold Vase', 'slug' => 'sold-vase', 'status' => ListingStatus::Sold, 'quantity' => 0]);

    $response = $this->get('/');

    $response->assertOk();
    $response->assertDontSee('Featured');
});

it('preloads the two latin webfont subsets ahead of the stylesheet', function (): void {
    $response = $this->get('/');

    $response->assertOk();

    $html = (string) $response->getContent();
    $preloads = substr_count($html, 'rel="preload" as="font"');
    // Vite hashes the built filename, so anchor on the tag, not the name.
    // Both markers must exist before their order means anything.
    expect($html)->toContain('rel="preload" as="font"')->toContain('rel="stylesheet"');

    $firstPreload = (int) strpos($html, 'rel="preload" as="font"');
    $stylesheet = (int) strpos($html, 'rel="stylesheet"');

    // Both faces, each carrying `crossorigin` — a font preload without it
    // fetches a copy the page cannot use — and both ahead of the stylesheet,
    // which is the whole point: discovery must not wait on CSS parsing.
    expect($preloads)->toBe(2)
        ->and($html)->toContain('young-serif-latin.woff2')
        ->and($html)->toContain('karla-latin.woff2')
        ->and(substr_count($html, 'as="font" type="font/woff2"'))->toBe(2)
        ->and($firstPreload)->toBeLessThan($stylesheet);
});

it('preloads no extended-latin subset, which most visits never reach', function (): void {
    $response = $this->get('/');

    expect((string) $response->getContent())->not->toContain('rel="preload" as="font" type="font/woff2" href="'.asset('fonts/karla-latin-ext.woff2'));
});
