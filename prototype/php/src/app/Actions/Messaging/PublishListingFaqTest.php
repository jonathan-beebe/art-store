<?php

declare(strict_types=1);

namespace App\Actions\Messaging;

use App\Domain\Messaging\FaqDraft;
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
