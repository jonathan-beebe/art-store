<?php

declare(strict_types=1);

namespace App\Models;

it('reads the message an answer was lifted from, or none when published from scratch', function (bool $hasSourceMessage): void {
    $message = $hasSourceMessage ? Message::factory()->create() : null;
    $faq = $message !== null
        ? ListingFaq::factory()->fromMessage($message)->create()
        : ListingFaq::factory()->create();

    if ($hasSourceMessage) {
        expect($faq->sourceMessage?->is($message))->toBeTrue();
    } else {
        expect($faq->sourceMessage)->toBeNull();
    }
})->with([
    'lifted from a message' => [true],
    'published from scratch' => [false],
]);
