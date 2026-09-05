<?php

declare(strict_types=1);

namespace App\Configurator;

it('names every field passed to it, in order', function (): void {
    $presented = PresentedPublishIssue::of('A plain sentence.', 'Fix it in Choices', 'https://example.test/fix');

    expect($presented->message)->toBe('A plain sentence.')
        ->and($presented->fixLabel)->toBe('Fix it in Choices')
        ->and($presented->fixUrl)->toBe('https://example.test/fix');
});
