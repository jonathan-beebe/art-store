<?php

declare(strict_types=1);

namespace App\Actions\Messaging;

use App\Domain\Messaging\FaqDraft;
use App\Models\ListingFaq;
use DateTimeImmutable;

final readonly class UpdateListingFaq
{
    public function __invoke(ListingFaq $faq, FaqDraft $draft, DateTimeImmutable $now): ListingFaq
    {
        $faq->update([
            'question' => $draft->question,
            'answer' => $draft->answer,
        ]);

        return $faq;
    }
}
