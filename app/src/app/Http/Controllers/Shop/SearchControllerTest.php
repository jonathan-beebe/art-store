<?php

declare(strict_types=1);

namespace App\Http\Controllers\Shop;

use App\Actions\Listings\RemoveListing;
use App\Domain\Listings\ListingRemovalKind;

it('searches titles descriptions and media attribute labels', function (): void {
    $seller = $this->seller();
    $this->listing($seller, ['title' => 'Harbour at Dawn', 'description' => 'A quiet morning.']);
    $this->listing($seller, ['title' => 'Kiln Study', 'description' => 'Fired in a harbour town.']);
    $field = $this->listing($seller, ['title' => 'Field Notes', 'description' => 'Pencil on paper.']);
    $this->mediumAttribute($field, 'Harbour Blue Print');
    $this->listing($seller, ['title' => 'Winter Elm', 'description' => 'Bare branches.']);

    $response = $this->get('/search?q=harbour');

    $response->assertOk();
    $response->assertSee('Harbour at Dawn');
    $response->assertSee('Kiln Study');
    $response->assertSee('Field Notes');
    $response->assertDontSee('Winter Elm');
});

it('treats a search of only wildcards as no search', function (): void {
    $seller = $this->seller();
    $this->listing($seller, ['title' => 'Harbour at Dawn']);
    $this->listing($seller, ['title' => 'Winter Elm']);

    $response = $this->get('/search?'.http_build_query(['q' => '%%%']));

    $response->assertSee('Harbour at Dawn');
    $response->assertSee('Winter Elm');
});

it('drops a removed listing from search results', function (): void {
    $seller = $this->seller();
    $listing = $this->listing($seller, ['title' => 'Harbour Study', 'description' => 'A harbour scene.']);
    app(RemoveListing::class)($listing, ListingRemovalKind::Temporary, 'Under review.');

    $response = $this->get('/search?q=harbour');

    $response->assertDontSee('Harbour Study');
});

it('shows a gentle empty state with no results for a term that matches nothing', function (): void {
    $this->listing($this->seller(), ['title' => 'Harbour at Dawn']);

    $response = $this->get('/search?q=zzz-nothing-matches');

    $response->assertOk();
    $response->assertSee('No art matches that yet.');
});

it('runs no query and shows a prompt rather than an empty state for an absent q', function (): void {
    $this->listing($this->seller(), ['title' => 'Harbour at Dawn']);

    $response = $this->get('/search');

    $response->assertOk();
    $response->assertDontSee('Harbour at Dawn');
    $response->assertDontSee('No art matches that yet.');
});

it('runs no query for a blank q', function (): void {
    $response = $this->get('/search?q=');

    $response->assertOk();
    $response->assertDontSee('No art matches that yet.');
});

it('titles the page', function (): void {
    $response = $this->get('/search?q=harbour');

    $response->assertSee('<title>Search — Art Store</title>', escape: false);
});

it('paginates at twelve listings', function (): void {
    $seller = $this->seller();
    for ($index = 1; $index <= 13; $index++) {
        $this->listing($seller, ['title' => sprintf('Harbour Study No %02d', $index), 'price_cents' => 1000 + $index]);
    }

    $first = $this->get('/search?q=harbour');
    $second = $this->get('/search?q=harbour&page=2');

    $first->assertSee('Harbour Study No 13');
    $first->assertDontSee('Harbour Study No 01');
    $second->assertSee('Harbour Study No 01');
});
