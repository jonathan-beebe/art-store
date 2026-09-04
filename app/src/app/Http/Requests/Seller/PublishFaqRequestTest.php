<?php

declare(strict_types=1);

namespace App\Http\Requests\Seller;

use App\Models\Conversation;
use App\Models\Message;

it('refuses a question or an answer past the domain limit', function (): void {
    $seller = $this->seller();
    $listing = $this->listing($seller);

    $response = $this->actingAs($seller, 'seller')->post("/seller/listings/{$listing->id}/faqs", [
        'question' => str_repeat('a', 501),
        'answer' => str_repeat('a', 2001),
    ]);

    $response->assertSessionHasErrors(['question', 'answer']);
});

it('refuses a source message from another listings thread', function (): void {
    $seller = $this->seller();
    $listing = $this->listing($seller);
    $message = Message::factory()->create();

    $response = $this->actingAs($seller, 'seller')->post("/seller/listings/{$listing->id}/faqs", [
        'question' => 'Do you ship internationally?',
        'answer' => 'Yes, worldwide.',
        'source_message_id' => $message->id,
    ]);

    $response->assertSessionHasErrors('source_message_id');
});

it('answers another sellers listing before it validates the form', function (): void {
    $listing = $this->listing($this->seller('Other Studio'));

    $response = $this->actingAs($this->seller(), 'seller')->post("/seller/listings/{$listing->id}/faqs", []);

    $response->assertNotFound();
    $response->assertSessionHasNoErrors();
});

it('reads the draft the seller typed', function (): void {
    $request = PublishFaqRequest::create('/seller/listings/1/faqs', 'POST', [
        'question' => 'Do you ship internationally?',
        'answer' => 'Yes, worldwide.',
    ]);

    expect($request->draft()->question)->toBe('Do you ship internationally?')
        ->and($request->draft()->answer)->toBe('Yes, worldwide.');
});

it('reads no source message when the form sends none', function (): void {
    $request = PublishFaqRequest::create('/seller/listings/1/faqs', 'POST', [
        'question' => 'Do you ship internationally?',
        'answer' => 'Yes, worldwide.',
    ]);

    expect($request->sourceMessage())->toBeNull();
});

it('reads the thread the disclosure was opened from', function (): void {
    $conversation = Conversation::factory()->listingQuestion()->create();
    $request = PublishFaqRequest::create('/seller/listings/1/faqs', 'POST', [
        'question' => 'Do you ship internationally?',
        'answer' => 'Yes, worldwide.',
        'conversation_id' => $conversation->id,
    ]);

    expect($request->conversation()?->id)->toBe($conversation->id);
});

it('reads no thread when the form sends none, the listings own faq page', function (): void {
    $request = PublishFaqRequest::create('/seller/listings/1/faqs', 'POST', [
        'question' => 'Do you ship internationally?',
        'answer' => 'Yes, worldwide.',
    ]);

    expect($request->conversation())->toBeNull();
});
