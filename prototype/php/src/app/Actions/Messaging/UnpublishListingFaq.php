<?php

declare(strict_types=1);

namespace App\Actions\Messaging;

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
        $faq->delete();
    }
}
