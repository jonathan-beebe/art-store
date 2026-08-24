<?php

declare(strict_types=1);

namespace App\Http\Controllers\Seller;

use App\Domain\Messaging\ConversationSubject;
use App\Models\Conversation;
use App\Models\ListingFaq;
use App\Models\Message;

it('lists the published entries with edit and unpublish forms', function (): void {
    $seller = $this->seller();
    $listing = $this->listing($seller);
    $faq = ListingFaq::factory()->create([
        'listing_id' => $listing->id,
        'question' => 'Is this framed?',
        'answer' => 'Yes, in a black wood frame.',
    ]);

    $response = $this->actingAs($seller, 'seller')->get("/seller/listings/{$listing->id}/faqs");

    $response->assertOk();
    $response->assertSee('Is this framed?');
    $response->assertSee('Yes, in a black wood frame.');
    $response->assertSee(route('seller.listings.faqs.update', [$listing, $faq]));
    $response->assertSee(route('seller.listings.faqs.destroy', [$listing, $faq]));
});

it('refuses another sellers listing faqs page', function (): void {
    $listing = $this->listing($this->seller('Other Studio'));

    $response = $this->actingAs($this->seller(), 'seller')->get("/seller/listings/{$listing->id}/faqs");

    $response->assertNotFound();
});

it('publishes a new entry', function (): void {
    $seller = $this->seller();
    $listing = $this->listing($seller);
    $customer = $this->verifiedCustomer();
    $conversation = Conversation::factory()
        ->forSubject(ConversationSubject::listingQuestion($seller->id, $customer->id, $listing->id))
        ->create();
    $message = Message::factory()->from($seller)->create(['conversation_id' => $conversation->id]);

    $response = $this->actingAs($seller, 'seller')->post("/seller/listings/{$listing->id}/faqs", [
        'question' => 'Do you ship internationally?',
        'answer' => 'Yes, worldwide.',
        'source_message_id' => $message->id,
    ]);

    $response->assertRedirect(route('seller.listings.faqs.index', $listing));
    $response->assertSessionHas('status', 'Published to the listing.');
    $faq = ListingFaq::where('listing_id', $listing->id)->sole();
    expect($faq->question)->toBe('Do you ship internationally?')
        ->and($faq->answer)->toBe('Yes, worldwide.')
        ->and($faq->source_message_id)->toBe($message->id);
});

it('publishes with no source message', function (): void {
    $seller = $this->seller();
    $listing = $this->listing($seller);

    $this->actingAs($seller, 'seller')->post("/seller/listings/{$listing->id}/faqs", [
        'question' => 'Do you ship internationally?',
        'answer' => 'Yes, worldwide.',
    ]);

    expect(ListingFaq::where('listing_id', $listing->id)->sole()->source_message_id)->toBeNull();
});

it('refuses to publish against another sellers listing', function (): void {
    $listing = $this->listing($this->seller('Other Studio'));

    $response = $this->actingAs($this->seller(), 'seller')->post("/seller/listings/{$listing->id}/faqs", [
        'question' => 'Sneaking in?',
        'answer' => 'Should not land.',
    ]);

    $response->assertNotFound();
    expect(ListingFaq::count())->toBe(0);
});

it('rewords a published entry', function (): void {
    $seller = $this->seller();
    $listing = $this->listing($seller);
    $faq = ListingFaq::factory()->create(['listing_id' => $listing->id]);

    $response = $this->actingAs($seller, 'seller')->put("/seller/listings/{$listing->id}/faqs/{$faq->id}", [
        'question' => 'Updated question?',
        'answer' => 'Updated answer.',
    ]);

    $response->assertRedirect(route('seller.listings.faqs.index', $listing));
    $updated = $faq->fresh();
    expect($updated?->question)->toBe('Updated question?')
        ->and($updated?->answer)->toBe('Updated answer.');
});

it('answers not found rewording a faq that is not on the listing', function (): void {
    $seller = $this->seller();
    $listing = $this->listing($seller);
    $otherListing = $this->listing($seller);
    $faq = ListingFaq::factory()->create(['listing_id' => $otherListing->id]);

    $response = $this->actingAs($seller, 'seller')->put("/seller/listings/{$listing->id}/faqs/{$faq->id}", [
        'question' => 'Updated question?',
        'answer' => 'Updated answer.',
    ]);

    $response->assertNotFound();
});

it('unpublishes an entry by deleting the row', function (): void {
    $seller = $this->seller();
    $listing = $this->listing($seller);
    $faq = ListingFaq::factory()->create(['listing_id' => $listing->id]);

    $response = $this->actingAs($seller, 'seller')->delete("/seller/listings/{$listing->id}/faqs/{$faq->id}");

    $response->assertRedirect(route('seller.listings.faqs.index', $listing));
    expect(ListingFaq::find($faq->id))->toBeNull();
});

it('answers not found unpublishing a faq that is not on the listing', function (): void {
    $seller = $this->seller();
    $listing = $this->listing($seller);
    $faq = ListingFaq::factory()->create(['listing_id' => $this->listing($seller)->id]);

    $response = $this->actingAs($seller, 'seller')->delete("/seller/listings/{$listing->id}/faqs/{$faq->id}");

    $response->assertNotFound();
    expect(ListingFaq::find($faq->id))->not->toBeNull();
});

it('refuses to unpublish another sellers entry', function (): void {
    $listing = $this->listing($this->seller('Other Studio'));
    $faq = ListingFaq::factory()->create(['listing_id' => $listing->id]);

    $response = $this->actingAs($this->seller(), 'seller')->delete("/seller/listings/{$listing->id}/faqs/{$faq->id}");

    $response->assertNotFound();
    expect(ListingFaq::find($faq->id))->not->toBeNull();
});
