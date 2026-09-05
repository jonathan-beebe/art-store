<?php

declare(strict_types=1);

namespace App\Actions\Messaging;

use App\Logging\Story;
use App\Logging\StoryEvent;
use App\Models\ListingFaq;
use DateTimeImmutable;

/**
 * A published entry has no draft state, so taking it down is a delete rather
 * than a status change.
 */
final readonly class UnpublishListingFaq
{
    public function __invoke(ListingFaq $faq, DateTimeImmutable $now): void
    {
        Story::for(StoryEvent::FaqUnpublish)->tell('taking a listing FAQ down', [
            'listing_id' => $faq->listing_id,
            'listing_faq_id' => $faq->id,
        ], function (Story $story) use ($faq): void {
            $faq->delete();

            $story->did('took the listing FAQ down', [
                'listing_id' => $faq->listing_id,
                'listing_faq_id' => $faq->id,
            ]);
        });
    }
}
