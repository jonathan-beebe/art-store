<?php

declare(strict_types=1);

namespace App\Http\Controllers\Shop;

use App\Domain\Listings\ListingEventType;
use App\Domain\Listings\ListingStatus;
use App\Models\ListingEvent;

it('shows the listing in full', function (): void {
    $listing = $this->listing($this->seller('Blue Kiln Studio'), [
        'title' => 'Harbour at Dawn',
        'slug' => 'harbour-at-dawn',
        'description' => 'A quiet morning over the water.',
        'medium' => 'oil',
        'dimensions' => '12 x 16 in',
        'price_cents' => 24500,
    ]);

    $response = $this->get('/art/'.$listing->slug);

    $response->assertOk();
    $response->assertSee('Harbour at Dawn');
    $response->assertSee('Blue Kiln Studio');
    $response->assertSee('A quiet morning over the water.');
    $response->assertSee('oil');
    $response->assertSee('12 x 16 in');
    $response->assertSee('$245.00');
});

it('records a view event for the visitor', function (): void {
    $visitor = $this->visitor();
    $listing = $this->listing($this->seller(), ['slug' => 'harbour-at-dawn']);

    $this->get('/art/harbour-at-dawn');

    $event = ListingEvent::sole();
    expect($event->type)->toBe(ListingEventType::View)
        ->and($event->listing_id)->toBe($listing->id)
        ->and($event->customer_id)->toBe($visitor->id);
});

it('says a sold listing is sold and offers no cart button', function (): void {
    $this->listing($this->seller(), [
        'slug' => 'sold-vase',
        'status' => ListingStatus::Sold,
        'quantity' => 0,
    ]);

    $response = $this->get('/art/sold-vase');

    $response->assertOk();
    $response->assertSee('Sold');
    $response->assertDontSee('Add to cart');
});

it('keeps a draft listing off the storefront', function (): void {
    $this->listing($this->seller(), ['slug' => 'unfinished', 'status' => ListingStatus::Draft]);

    $this->get('/art/unfinished')->assertNotFound();
});
