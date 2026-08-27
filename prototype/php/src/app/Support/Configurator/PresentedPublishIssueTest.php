<?php

declare(strict_types=1);

namespace App\Support\Configurator;

it('carries a message, a fix label, and a fix url', function (): void {
    $presented = PresentedPublishIssue::of('A plain sentence.', 'Fix it in Choices', 'https://example.test/fix');

    expect($presented->message)->toBe('A plain sentence.')
        ->and($presented->fixLabel)->toBe('Fix it in Choices')
        ->and($presented->fixUrl)->toBe('https://example.test/fix');
});
