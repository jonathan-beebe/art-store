<?php

declare(strict_types=1);

namespace App\Actions\Messaging;

use App\Domain\Messaging\ConversationStatus;
use App\Domain\Messaging\FaqDraft;
use App\Logging\StoryEvent;
use App\Models\Listing;
use App\Models\ListingFaq;
use App\Models\Message;
use App\Support\Story;
use DateTimeImmutable;

final readonly class PublishListingFaq
{
    public function __construct(private ResolveConversation $resolveConversation) {}

    public function __invoke(Listing $listing, FaqDraft $draft, ?Message $sourceMessage, DateTimeImmutable $now): ListingFaq
    {
        return Story::for(StoryEvent::FaqPublish)->tell('publishing a listing FAQ', [
            'listing_id' => $listing->id,
        ], function (Story $story) use ($listing, $draft, $sourceMessage, $now): ListingFaq {
            $faq = $listing->faqs()->create([
                'seller_id' => $listing->seller_id,
                'question' => $draft->question,
                'answer' => $draft->answer,
                'source_message_id' => $sourceMessage?->id,
                'published_at' => $now,
            ]);

            $story->did('published the listing FAQ', [
                'listing_id' => $listing->id,
                'listing_faq_id' => $faq->id,
                'source_message_id' => $sourceMessage?->id,
            ]);

            $this->resolveSourceThread($listing, $sourceMessage, $now);

            return $faq;
        });
    }

    /**
     * The answer is on the listing for everyone, which is what answering the
     * question meant — but only when the thread is still open: publishing a
     * second answer to an already-resolved question resolves nothing twice.
     */
    private function resolveSourceThread(Listing $listing, ?Message $sourceMessage, DateTimeImmutable $now): void
    {
        if ($sourceMessage === null) {
            return;
        }

        $conversation = $sourceMessage->loadMissing('conversation')->conversation;

        if (ConversationStatus::of($conversation->resolved_at) === ConversationStatus::Resolved) {
            return;
        }

        ($this->resolveConversation)($conversation, $listing->loadMissing('seller')->seller, $now);
    }
}
