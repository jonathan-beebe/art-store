<?php

declare(strict_types=1);

it('answers not found for a specimen it does not know', function (): void {
    $this->get('/design-system/specimens/carousel-of-wonders')->assertNotFound();
});

it('renders every specimen with its empty-catalog note before any listing exists', function (): void {
    $this->get('/design-system/specimens/browse-sheet')->assertOk()->assertSee('No attributed medium yet');
    $this->get('/design-system/specimens/cover-rail')->assertOk()->assertSee('No attributed medium yet');
    $this->get('/design-system/specimens/buy-bar')->assertOk()->assertSee('No for-sale listing yet');
    $this->get('/design-system/specimens/swipe-gallery')->assertOk()->assertSee('No for-sale listing yet');
});

it('renders the pickers with live media and covers', function (): void {
    $listing = $this->listing($this->seller(), ['title' => 'Burrow Kitchen Tea Bowl']);
    $this->mediumAttribute($listing, 'Ceramic');
    $this->listingImage($listing);

    $sheet = $this->get('/design-system/specimens/browse-sheet');
    $sheet->assertOk();
    $sheet->assertSee('Browse media');
    $sheet->assertSee('Burrow Kitchen Tea Bowl');

    $rail = $this->get('/design-system/specimens/cover-rail');
    $rail->assertOk();
    $rail->assertSee('Ceramic');
    $rail->assertSee('All art');
    $rail->assertSee("background-image: url('".$listing->imageUrl()."')", escape: false);
});

it('renders the buy bar with the listing price pinned alongside Add to cart', function (): void {
    $this->listing($this->seller(), ['title' => 'Burrow Kitchen Tea Bowl', 'price_cents' => 14000]);

    $response = $this->get('/design-system/specimens/buy-bar');

    $response->assertOk();
    $response->assertSee('Burrow Kitchen Tea Bowl');
    $response->assertSee('$140.00');
    $response->assertSee('Add to cart');
});

it('renders the gallery as a swipeable carousel once a listing has more than one photo', function (): void {
    $listing = $this->listing($this->seller(), ['title' => 'Burrow Kitchen Tea Bowl']);
    $this->listingImage($listing, ['position' => 0]);
    $this->listingImage($listing, ['position' => 1]);

    $response = $this->get('/design-system/specimens/swipe-gallery');

    $response->assertOk();
    $response->assertSee('Burrow Kitchen Tea Bowl — photo 1');
    $response->assertSee('Burrow Kitchen Tea Bowl — photo 2');
});

it('shows the single photo with a nudge when a listing has no gallery yet', function (): void {
    $this->listing($this->seller(), ['title' => 'Burrow Kitchen Tea Bowl']);

    $response = $this->get('/design-system/specimens/swipe-gallery');

    $response->assertOk();
    $response->assertSee('One photo on this listing');
});

it('marks the buy bar, swipe gallery, and cover rail specimens as unshipped explorations on their own page', function (): void {
    $this->get('/design-system/specimens/buy-bar')->assertOk()->assertSee('Exploration — not shipped', false);
    $this->get('/design-system/specimens/swipe-gallery')->assertOk()->assertSee('Exploration — not shipped', false);
    $this->get('/design-system/specimens/cover-rail')->assertOk()->assertSee('Exploration — not shipped', false);
});
