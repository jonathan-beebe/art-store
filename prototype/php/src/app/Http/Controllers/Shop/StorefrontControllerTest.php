<?php

declare(strict_types=1);

namespace App\Http\Controllers\Shop;

use App\Actions\Listings\RemoveListing;
use App\Domain\Listings\ListingRemovalKind;
use App\Domain\Listings\ListingStatus;
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

it('drops a removed listing from search results', function (): void {
    $seller = $this->seller();
    $listing = $this->listing($seller, ['title' => 'Harbour Study', 'description' => 'A harbour scene.']);
    app(RemoveListing::class)($listing, ListingRemovalKind::Temporary, 'Under review.');

    $response = $this->get('/?q=harbour');

    $response->assertDontSee('Harbour Study');
});

it('searches titles descriptions and media attribute labels', function (): void {
    $seller = $this->seller();
    $this->listing($seller, ['title' => 'Harbour at Dawn', 'description' => 'A quiet morning.']);
    $this->listing($seller, ['title' => 'Kiln Study', 'description' => 'Fired in a harbour town.']);
    $field = $this->listing($seller, ['title' => 'Field Notes', 'description' => 'Pencil on paper.']);
    $this->mediumAttribute($field, 'Harbour Blue Print');
    $this->listing($seller, ['title' => 'Winter Elm', 'description' => 'Bare branches.']);

    $response = $this->get('/?q=harbour');

    $response->assertSee('Harbour at Dawn');
    $response->assertSee('Kiln Study');
    $response->assertSee('Field Notes');
    $response->assertDontSee('Winter Elm');
});

it('treats a search of only wildcards as no search', function (): void {
    $seller = $this->seller();
    $this->listing($seller, ['title' => 'Harbour at Dawn']);
    $this->listing($seller, ['title' => 'Winter Elm']);

    $response = $this->get('/?'.http_build_query(['q' => '%%%']));

    $response->assertSee('Harbour at Dawn');
    $response->assertSee('Winter Elm');
});

it('narrows to one medium', function (): void {
    $seller = $this->seller();
    $oil = $this->listing($seller, ['title' => 'Harbour at Dawn']);
    $ceramic = $this->listing($seller, ['title' => 'Kiln Study']);
    $this->mediumAttribute($oil, 'Oil');
    $this->mediumAttribute($ceramic, 'Ceramic');

    $response = $this->get('/?medium=ceramic');

    $response->assertSee('Kiln Study');
    $response->assertDontSee('Harbour at Dawn');
});

it('shows an empty storefront when the medium filter matches nothing', function (): void {
    $seller = $this->seller();
    $listing = $this->listing($seller, ['title' => 'Harbour at Dawn']);
    $this->mediumAttribute($listing, 'Oil');

    $response = $this->get('/?medium=bronze');

    $response->assertDontSee('Harbour at Dawn');
});

it('leaves a listing with no Medium attribute out of every medium filter', function (): void {
    $seller = $this->seller();
    $this->listing($seller, ['title' => 'Unattributed Piece']);

    $response = $this->get('/?medium=oil');

    $response->assertDontSee('Unattributed Piece');
});

it('offers the media of listings that are for sale', function (): void {
    $seller = $this->seller();
    $forSale = $this->listing($seller);
    $draft = $this->listing($seller, ['status' => ListingStatus::Draft]);
    $this->mediumAttribute($forSale, 'Ceramic');
    $this->mediumAttribute($draft, 'Linocut');

    $response = $this->get('/');

    $response->assertSee('<option value="ceramic"', escape: false);
    $response->assertDontSee('<option value="linocut"', escape: false);
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
