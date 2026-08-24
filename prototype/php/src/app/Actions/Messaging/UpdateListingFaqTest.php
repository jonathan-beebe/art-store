<?php

declare(strict_types=1);

namespace App\Actions\Messaging;

use App\Domain\Messaging\FaqDraft;
use App\Models\ListingFaq;

it('rewords a published entry', function (): void {
    $faq = ListingFaq::factory()->create(['question' => 'Old question?', 'answer' => 'Old answer.']);
    $draft = FaqDraft::of('New question?', 'New answer.');

    $updated = app(UpdateListingFaq::class)($faq, $draft, $this->moment('2026-08-20 10:00:00'));

    expect($updated->question)->toBe('New question?')
        ->and($updated->answer)->toBe('New answer.')
        ->and($faq->fresh()?->question)->toBe('New question?');
});

it('leaves when it was published untouched', function (): void {
    $faq = ListingFaq::factory()->create(['published_at' => $this->moment('2026-08-01 09:00:00')]);
    $draft = FaqDraft::of('New question?', 'New answer.');

    app(UpdateListingFaq::class)($faq, $draft, $this->moment('2026-08-20 10:00:00'));

    expect($faq->fresh()?->published_at?->format('Y-m-d H:i:s'))->toBe('2026-08-01 09:00:00');
});
