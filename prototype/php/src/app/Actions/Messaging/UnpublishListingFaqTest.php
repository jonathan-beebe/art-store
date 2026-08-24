<?php

declare(strict_types=1);

namespace App\Actions\Messaging;

use App\Models\ListingFaq;

it('deletes the published entry', function (): void {
    $faq = ListingFaq::factory()->create();

    app(UnpublishListingFaq::class)($faq, $this->moment('2026-08-20 10:00:00'));

    expect(ListingFaq::find($faq->id))->toBeNull();
});
