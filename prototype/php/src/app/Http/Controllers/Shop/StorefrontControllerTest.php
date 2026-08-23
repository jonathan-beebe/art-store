<?php

declare(strict_types=1);

namespace App\Http\Controllers\Shop;

use App\Domain\Listings\ListingStatus;

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

it('searches titles descriptions and media', function (): void {
    $seller = $this->seller();
    $this->listing($seller, ['title' => 'Harbour at Dawn', 'description' => 'A quiet morning.', 'medium' => 'oil']);
    $this->listing($seller, ['title' => 'Kiln Study', 'description' => 'Fired in a harbour town.', 'medium' => 'ceramic']);
    $this->listing($seller, ['title' => 'Field Notes', 'description' => 'Pencil on paper.', 'medium' => 'harbour-blue print']);
    $this->listing($seller, ['title' => 'Winter Elm', 'description' => 'Bare branches.', 'medium' => 'watercolour']);

    $response = $this->get('/?q=harbour');

    $response->assertSee('Harbour at Dawn');
    $response->assertSee('Kiln Study');
    $response->assertSee('Field Notes');
    $response->assertDontSee('Winter Elm');
});

it('narrows to one medium', function (): void {
    $seller = $this->seller();
    $this->listing($seller, ['title' => 'Harbour at Dawn', 'medium' => 'oil']);
    $this->listing($seller, ['title' => 'Kiln Study', 'medium' => 'ceramic']);

    $response = $this->get('/?medium=ceramic');

    $response->assertSee('Kiln Study');
    $response->assertDontSee('Harbour at Dawn');
});

it('offers the media of listings that are for sale', function (): void {
    $seller = $this->seller();
    $this->listing($seller, ['medium' => 'ceramic']);
    $this->listing($seller, ['medium' => 'linocut', 'status' => ListingStatus::Draft]);

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
