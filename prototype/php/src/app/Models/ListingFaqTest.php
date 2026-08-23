<?php

declare(strict_types=1);

namespace App\Models;

it('reads the listing it answers a question about', function (): void {
    $listing = $this->listing($this->seller());
    $faq = ListingFaq::factory()->create(['listing_id' => $listing->id]);

    expect($faq->listing->is($listing))->toBeTrue();
});

it('reads the message an answer was lifted from', function (): void {
    $message = Message::factory()->create();
    $faq = ListingFaq::factory()->fromMessage($message)->create();

    expect($faq->sourceMessage?->is($message))->toBeTrue();
});

it('has no source message when published from scratch', function (): void {
    expect(ListingFaq::factory()->create()->sourceMessage)->toBeNull();
});
