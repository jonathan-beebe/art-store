<?php

declare(strict_types=1);

namespace App\Actions\Messaging;

use App\Domain\Messaging\FaqDraft;
use App\Models\Conversation;
use App\Models\Message;

it('publishes a question and answer to the listing', function (): void {
    $listing = $this->listing($this->seller());
    $draft = FaqDraft::of('Does this ship internationally?', 'Yes, worldwide.');

    $faq = app(PublishListingFaq::class)($listing, $draft, null, $this->moment('2026-08-20 10:00:00'));

    expect($faq->listing_id)->toBe($listing->id)
        ->and($faq->question)->toBe('Does this ship internationally?')
        ->and($faq->answer)->toBe('Yes, worldwide.')
        ->and($faq->source_message_id)->toBeNull()
        ->and($faq->published_at->format('Y-m-d H:i:s'))->toBe('2026-08-20 10:00:00');
});

it('records which message an answer was lifted from', function (): void {
    $listing = $this->listing($this->seller());
    $message = Message::factory()->create();
    $draft = FaqDraft::of('Does this ship internationally?', 'Yes, worldwide.');

    $faq = app(PublishListingFaq::class)($listing, $draft, $message, $this->moment('2026-08-20 10:00:00'));

    expect($faq->source_message_id)->toBe($message->id);
});

it('resolves the open thread the source message belongs to', function (): void {
    $seller = $this->seller();
    $conversation = Conversation::factory()->listingQuestion()->create(['seller_id' => $seller->id]);
    $message = Message::factory()->create(['conversation_id' => $conversation->id]);
    $listing = $this->listing($seller);
    $draft = FaqDraft::of('Does this ship internationally?', 'Yes, worldwide.');

    app(PublishListingFaq::class)($listing, $draft, $message, $this->moment('2026-08-20 10:00:00'));

    expect($conversation->fresh()?->resolved_at?->format('Y-m-d H:i:s'))->toBe('2026-08-20 10:00:00');
});

it('leaves an already-resolved source thread alone', function (): void {
    $seller = $this->seller();
    $conversation = Conversation::factory()->listingQuestion()->create([
        'seller_id' => $seller->id,
        'resolved_at' => $this->moment('2026-08-19 09:00:00'),
    ]);
    $message = Message::factory()->create(['conversation_id' => $conversation->id]);
    $listing = $this->listing($seller);
    $draft = FaqDraft::of('Does this ship internationally?', 'Yes, worldwide.');

    app(PublishListingFaq::class)($listing, $draft, $message, $this->moment('2026-08-20 10:00:00'));

    expect($conversation->fresh()?->resolved_at?->format('Y-m-d H:i:s'))->toBe('2026-08-19 09:00:00');
});

it('leaves no thread to resolve when publishing with no source message', function (): void {
    $seller = $this->seller();
    $listing = $this->listing($seller);
    $draft = FaqDraft::of('Does this ship internationally?', 'Yes, worldwide.');

    $faq = app(PublishListingFaq::class)($listing, $draft, null, $this->moment('2026-08-20 10:00:00'));

    expect($faq->source_message_id)->toBeNull();
});
