<?php

declare(strict_types=1);

namespace App\Http\Controllers\Shop;

use App\Domain\Listings\ListingEventType;
use App\Domain\Listings\ListingStatus;
use App\Models\ListingEvent;
use App\Models\ListingFaq;
use Tests\CapturedStory;

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

it('collapses a second view within the hour into no row, logged as a refusal', function (): void {
    $this->listing($this->seller(), ['slug' => 'harbour-at-dawn']);
    $log = CapturedStory::capture();

    $this->travelTo($this->moment('2026-08-20 09:00:00'));
    $first = $this->get('/art/harbour-at-dawn');
    $visitorCookie = $first->getCookie('customer_id')?->getValue();
    assert(is_string($visitorCookie));

    $this->travelTo($this->moment('2026-08-20 09:45:00'));
    $this->withCookie('customer_id', $visitorCookie)->get('/art/harbour-at-dawn');

    expect(ListingEvent::query()->where('type', ListingEventType::View)->count())->toBe(1);

    $refused = $log->line('listing.view', 'refused');
    expect($refused['level'])->toBe('debug');
});

it('records a view in the next hour as a row and a did line of its own', function (): void {
    $this->listing($this->seller(), ['slug' => 'harbour-at-dawn']);

    $this->travelTo($this->moment('2026-08-20 09:00:00'));
    $first = $this->get('/art/harbour-at-dawn');
    $visitorCookie = $first->getCookie('customer_id')?->getValue();
    assert(is_string($visitorCookie));

    $this->travelTo($this->moment('2026-08-20 10:00:00'));
    $this->withCookie('customer_id', $visitorCookie)->get('/art/harbour-at-dawn');

    expect(ListingEvent::query()->where('type', ListingEventType::View)->count())->toBe(2);
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

it('offers a form to ask the seller a question', function (): void {
    $listing = $this->listing($this->seller(), ['slug' => 'harbour-at-dawn']);

    $response = $this->get('/art/harbour-at-dawn');

    $response->assertSee('Ask the seller a question');
    $response->assertSee(route('shop.listing.questions', $listing), escape: false);
});

it('lists the sellers published questions and answers', function (): void {
    $listing = $this->listing($this->seller(), ['slug' => 'harbour-at-dawn']);
    ListingFaq::factory()->create([
        'listing_id' => $listing->id,
        'question' => 'Does this ship framed?',
        'answer' => 'Yes, in a black wood frame.',
        'published_at' => $this->moment('2026-08-20 09:00:00'),
    ]);

    $response = $this->get('/art/harbour-at-dawn');

    $response->assertSee('Does this ship framed?');
    $response->assertSee('Yes, in a black wood frame.');
});

it('shows no questions and answers section for a listing with none published', function (): void {
    $this->listing($this->seller(), ['slug' => 'harbour-at-dawn']);

    $response = $this->get('/art/harbour-at-dawn');

    $response->assertDontSee('Questions &amp; answers', escape: false);
});
