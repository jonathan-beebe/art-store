<?php

declare(strict_types=1);

namespace App\Actions\Messaging;

use App\Domain\Messaging\FaqDraft;
use App\Logging\StoryEvent;
use App\Models\Listing;
use App\Models\ListingFaq;
use App\Models\Message;
use App\Support\Story;
use DateTimeImmutable;

final readonly class PublishListingFaq
{
    public function __invoke(Listing $listing, FaqDraft $draft, ?Message $sourceMessage, DateTimeImmutable $now): ListingFaq
    {
        $story = Story::for(StoryEvent::FaqPublish)->will('publishing a listing FAQ', [
            'listing_id' => $listing->id,
        ]);

        $faq = $listing->faqs()->create([
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

        return $faq;
    }
}
