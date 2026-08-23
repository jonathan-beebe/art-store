<?php

declare(strict_types=1);

namespace App\Http\Controllers\Shop;

use App\Domain\Listings\ListingEventType;
use App\Models\Customer;
use App\Models\Favorite;
use App\Models\ListingEvent;

it('favorites a listing and records the event', function (): void {
    $visitor = $this->visitor();
    $listing = $this->listing($this->seller(), ['slug' => 'harbour-at-dawn']);

    $response = $this->post('/art/harbour-at-dawn/favorite');

    $response->assertRedirect();
    $favorite = Favorite::sole();
    expect($favorite->listing_id)->toBe($listing->id)
        ->and($favorite->customer_id)->toBe($visitor->id)
        ->and(ListingEvent::sole()->type)->toBe(ListingEventType::Favorite);
});

it('removes the favorite when favorited twice', function (): void {
    $this->visitor();
    $this->listing($this->seller(), ['slug' => 'harbour-at-dawn']);

    $this->post('/art/harbour-at-dawn/favorite');
    $this->post('/art/harbour-at-dawn/favorite');

    expect(Favorite::count())->toBe(0)
        ->and(ListingEvent::orderBy('id')->get()->pluck('type')->all())
        ->toBe([ListingEventType::Favorite, ListingEventType::Unfavorite]);
});

it('survives the merge when favorited before signing in', function (): void {
    $this->visitor();
    Customer::factory()->create(['email' => 'shopper@example.com']);
    $this->listing($this->seller(), ['slug' => 'harbour-at-dawn', 'title' => 'Harbour at Dawn']);
    $this->post('/art/harbour-at-dawn/favorite');

    $this->post('/login', ['email' => 'shopper@example.com']);
    $this->get(session('debug_magic_link'));

    $this->get('/favorites')->assertSee('Harbour at Dawn');
    expect(Favorite::count())->toBe(1);
});

it('lists the visitor favorites', function (): void {
    $this->visitor();
    $seller = $this->seller();
    $this->listing($seller, ['slug' => 'harbour-at-dawn', 'title' => 'Harbour at Dawn']);
    $this->listing($seller, ['slug' => 'winter-elm', 'title' => 'Winter Elm']);
    $this->post('/art/harbour-at-dawn/favorite');

    $response = $this->get('/favorites');

    $response->assertOk();
    $response->assertSee('Harbour at Dawn');
    $response->assertDontSee('Winter Elm');
});
