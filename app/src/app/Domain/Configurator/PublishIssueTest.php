<?php

declare(strict_types=1);

namespace App\Domain\Configurator;

it('carries a code and a message', function (): void {
    $issue = PublishIssue::of('too_many_variants', 'The listing holds more than 500 variants.');

    expect($issue->code)->toBe('too_many_variants')
        ->and($issue->message)->toBe('The listing holds more than 500 variants.')
        ->and($issue->subjectId)->toBeNull();
});

it('optionally names the row the issue is about', function (): void {
    $issue = PublishIssue::of('variant_priced_negative', 'Variant vrt_01 is priced below zero.', 'vrt_01');

    expect($issue->subjectId)->toBe('vrt_01');
});
