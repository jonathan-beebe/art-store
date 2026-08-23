<?php

declare(strict_types=1);

namespace App\Domain\Messaging;

it('carries the question, the answer, and which message the answer came from', function (): void {
    $prefill = FaqPrefill::of('Is this framed?', 'Yes, in a black wood frame.', 42);

    expect($prefill->question)->toBe('Is this framed?')
        ->and($prefill->answer)->toBe('Yes, in a black wood frame.')
        ->and($prefill->sourceMessageId)->toBe(42);
});
