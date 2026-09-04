<?php

declare(strict_types=1);

namespace App\Http\Requests\Seller;

use App\Models\ListingFaq;

it('refuses a question or an answer past the domain limit', function (): void {
    $seller = $this->seller();
    $listing = $this->listing($seller);
    $faq = ListingFaq::factory()->create(['listing_id' => $listing->id]);

    $response = $this->actingAs($seller, 'seller')->put("/seller/listings/{$listing->id}/faqs/{$faq->id}", [
        'question' => str_repeat('a', 501),
        'answer' => str_repeat('a', 2001),
    ]);

    $response->assertSessionHasErrors(['question', 'answer']);
    expect($faq->fresh()?->question)->not->toBe(str_repeat('a', 501));
});

it('answers another sellers listing before it validates the form', function (): void {
    $listing = $this->listing($this->seller('Other Studio'));
    $faq = ListingFaq::factory()->create(['listing_id' => $listing->id]);

    $response = $this->actingAs($this->seller(), 'seller')->put("/seller/listings/{$listing->id}/faqs/{$faq->id}", []);

    $response->assertNotFound();
    $response->assertSessionHasNoErrors();
});

it('reads the draft the seller typed', function (): void {
    $request = UpdateFaqRequest::create('/seller/listings/1/faqs/1', 'PUT', [
        'question' => 'Updated question?',
        'answer' => 'Updated answer.',
    ]);

    expect($request->draft()->question)->toBe('Updated question?')
        ->and($request->draft()->answer)->toBe('Updated answer.');
});
