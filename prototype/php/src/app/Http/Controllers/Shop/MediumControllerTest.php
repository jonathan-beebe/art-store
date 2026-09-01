<?php

declare(strict_types=1);

namespace App\Http\Controllers\Shop;

it('says a medium page with nothing on it has nothing on it', function (): void {
    // A medium only resolves once some for-sale listing carries it, so the
    // grid's own empty state shows only past the first page: one listing
    // establishes 'ceramic', and page 2 of that one-listing result is empty.
    $listing = $this->listing($this->seller());
    $this->mediumAttribute($listing, 'Ceramic');

    $response = $this->get('/medium/ceramic?page=2');

    $response->assertOk();
    $response->assertSee('No art matches that yet.');
    $response->assertDontSee('<article', escape: false);
});

it('narrows to one medium', function (): void {
    $seller = $this->seller();
    $oil = $this->listing($seller, ['title' => 'Harbour at Dawn']);
    $ceramic = $this->listing($seller, ['title' => 'Kiln Study']);
    $this->mediumAttribute($oil, 'Oil');
    $this->mediumAttribute($ceramic, 'Ceramic');

    $response = $this->get('/medium/ceramic');

    $response->assertOk();
    $response->assertSee('Kiln Study');
    $response->assertDontSee('Harbour at Dawn');
});

it('titles the page with the medium label', function (): void {
    $listing = $this->listing($this->seller());
    $this->mediumAttribute($listing, 'Ceramic');

    $response = $this->get('/medium/ceramic');

    $response->assertSee('<title>Ceramic — Art Store</title>', escape: false);
});

it('404s a medium no for-sale listing carries', function (): void {
    $response = $this->get('/medium/bronze');

    $response->assertNotFound();
});

it('leaves a listing with no Medium attribute out of every medium page', function (): void {
    $seller = $this->seller();
    $this->listing($seller, ['title' => 'Unattributed Piece']);
    $attributed = $this->listing($seller);
    $this->mediumAttribute($attributed, 'Oil');

    $response = $this->get('/medium/oil');

    $response->assertDontSee('Unattributed Piece');
});

it('paginates at twelve listings', function (): void {
    $seller = $this->seller();
    for ($index = 1; $index <= 13; $index++) {
        $listing = $this->listing($seller, ['title' => sprintf('Study No %02d', $index), 'price_cents' => 1000 + $index]);
        $this->mediumAttribute($listing, 'Ceramic');
    }

    $first = $this->get('/medium/ceramic');
    $second = $this->get('/medium/ceramic?page=2');

    $first->assertSee('Study No 13');
    $first->assertDontSee('Study No 01');
    $second->assertSee('Study No 01');
});
