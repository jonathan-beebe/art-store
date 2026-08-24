<?php

declare(strict_types=1);

namespace App\Http\Controllers\Shop;

use App\Domain\Listings\ListingEventType;
use App\Domain\Listings\ListingStatus;
use App\Models\Customer;
use App\Models\CustomerBlock;
use App\Models\Favorite;
use App\Models\ListingEvent;
use App\Models\ListingRemoval;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Session;

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

it('favorites a listing while blocked', function (): void {
    $visitor = $this->visitor();
    CustomerBlock::factory()->create(['customer_id' => $visitor->id]);
    $this->listing($this->seller(), ['slug' => 'harbour-at-dawn']);

    $this->post('/art/harbour-at-dawn/favorite');

    expect(Favorite::sole()->customer_id)->toBe($visitor->id);
});

it('removes the favorite when favorited twice', function (): void {
    $this->visitor();
    $this->listing($this->seller(), ['slug' => 'harbour-at-dawn']);

    $this->post('/art/harbour-at-dawn/favorite');
    $this->post('/art/harbour-at-dawn/favorite');

    expect(Favorite::count())->toBe(0)
        ->and(ListingEvent::orderBy('id')->pluck('type')->all())
        ->toBe([ListingEventType::Favorite, ListingEventType::Unfavorite]);
});

it('survives the merge when favorited before signing in', function (): void {
    $this->visitor();
    Customer::factory()->create(['email' => 'shopper@example.com']);
    $this->listing($this->seller(), ['slug' => 'harbour-at-dawn', 'title' => 'Harbour at Dawn']);
    $this->post('/art/harbour-at-dawn/favorite');

    $this->post('/login', ['email' => 'shopper@example.com']);
    $this->get(Arr::string(Session::all(), 'debug_magic_link'));

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

it('drops a listing an admin removed from the page and keeps the favorite', function (): void {
    $this->visitor();
    $seller = $this->seller();
    $this->listing($seller, ['slug' => 'harbour-at-dawn', 'title' => 'Harbour at Dawn']);
    $removed = $this->listing($seller, ['slug' => 'winter-elm', 'title' => 'Winter Elm']);
    $this->post('/art/harbour-at-dawn/favorite');
    $this->post('/art/winter-elm/favorite');
    ListingRemoval::factory()->create(['listing_id' => $removed->id]);

    $response = $this->get('/favorites');

    $response->assertOk();
    $response->assertSee('Harbour at Dawn');
    $response->assertDontSee('Winter Elm');
    // The save outlives the removal, so lifting one puts the card back.
    expect(Favorite::count())->toBe(2);
});

it('shows the card again once the removal is lifted', function (): void {
    $this->visitor();
    $listing = $this->listing($this->seller(), ['slug' => 'winter-elm', 'title' => 'Winter Elm']);
    $this->post('/art/winter-elm/favorite');
    $removal = ListingRemoval::factory()->create(['listing_id' => $listing->id]);

    $whileRemoved = $this->get('/favorites');
    $removal->update(['lifted_at' => now()]);
    $afterLift = $this->get('/favorites');

    $whileRemoved->assertDontSee('Winter Elm');
    $afterLift->assertSee('Winter Elm');
});

it('drops a listing the seller archived from the page', function (): void {
    $this->visitor();
    $listing = $this->listing($this->seller(), ['slug' => 'winter-elm', 'title' => 'Winter Elm']);
    $this->post('/art/winter-elm/favorite');
    $listing->update(['status' => ListingStatus::Archived]);

    $response = $this->get('/favorites');

    $response->assertOk();
    $response->assertDontSee('Winter Elm');
});

it('keeps a sold listing on the page', function (): void {
    $this->visitor();
    $listing = $this->listing($this->seller(), ['slug' => 'winter-elm', 'title' => 'Winter Elm']);
    $this->post('/art/winter-elm/favorite');
    $listing->update(['status' => ListingStatus::Sold, 'quantity' => 0]);

    $response = $this->get('/favorites');

    $response->assertOk();
    $response->assertSee('Winter Elm');
});
