<?php

declare(strict_types=1);

namespace App\Http\Controllers\Shop;

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

it('links the browsable root categories with their listing counts', function (): void {
    $jewelry = Category::factory()->create(['name' => 'Jewelry', 'path' => '/jewelry/']);
    Category::factory()->create(['name' => 'Hidden Room', 'path' => '/hidden-room/', 'browsable' => false]);
    $this->listing($this->seller(), ['category_id' => $jewelry->id]);

    $response = $this->get('/');

    $response->assertSee('href="'.route('shop.browse', ['categoryPath' => 'jewelry']).'"', escape: false);
    $response->assertDontSee('Hidden Room');
});

it('paginates at twelve listings', function (): void {
    $seller = $this->seller();
    for ($index = 1; $index <= 13; $index++) {
        $this->listing($seller, ['title' => sprintf('Study No %02d', $index), 'price_cents' => 1000 + $index]);
    }

    $first = $this->get('/');
    $second = $this->get('/?page=2');

    $first->assertSee('Study No 13');
    $first->assertDontSee('Study No 01');
    $second->assertSee('Study No 01');
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
