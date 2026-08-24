<?php

declare(strict_types=1);

namespace App\Actions\Messaging;

use App\Logging\StoryEvent;
use App\Models\ListingFaq;
use App\Support\Story;
use DateTimeImmutable;

/**
 * A published entry has no draft state, so taking it down is a delete rather
 * than a status change.
 */
final readonly class UnpublishListingFaq
{
    public function __invoke(ListingFaq $faq, DateTimeImmutable $now): void
    {
        $story = Story::for(StoryEvent::FaqUnpublish)->will('taking a listing FAQ down', [
            'listing_id' => $faq->listing_id,
            'listing_faq_id' => $faq->id,
        ]);

        $faq->delete();

        $story->did('took the listing FAQ down', [
            'listing_id' => $faq->listing_id,
            'listing_faq_id' => $faq->id,
        ]);
    }
}
