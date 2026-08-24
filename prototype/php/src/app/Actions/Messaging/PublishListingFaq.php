<?php

declare(strict_types=1);

namespace App\Actions\Messaging;

use App\Domain\Messaging\FaqDraft;
use App\Models\Listing;
use App\Models\ListingFaq;
use App\Models\Message;
use DateTimeImmutable;

final readonly class PublishListingFaq
{
    public function __invoke(Listing $listing, FaqDraft $draft, ?Message $sourceMessage, DateTimeImmutable $now): ListingFaq
    {
        return $listing->faqs()->create([
            'question' => $draft->question,
            'answer' => $draft->answer,
            'source_message_id' => $sourceMessage?->id,
            'published_at' => $now,
        ]);
    }
}
