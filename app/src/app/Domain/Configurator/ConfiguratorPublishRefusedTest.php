<?php

declare(strict_types=1);

namespace App\Domain\Configurator;

it('does nothing with no issues', function (): void {
    ConfiguratorPublishRefused::ifAny([]);
})->throwsNoExceptions();

it('names the single issue in its message', function (): void {
    $issue = PublishIssue::of('too_many_variants', 'The listing holds more than 500 variants.');

    expect(fn () => ConfiguratorPublishRefused::ifAny([$issue]))
        ->toThrow(ConfiguratorPublishRefused::class, 'This listing is not ready to publish: The listing holds more than 500 variants.');
});

it('counts the issues when there is more than one', function (): void {
    $issues = [
        PublishIssue::of('a', 'A'),
        PublishIssue::of('b', 'B'),
    ];

    expect(fn () => ConfiguratorPublishRefused::ifAny($issues))
        ->toThrow(ConfiguratorPublishRefused::class, 'This listing is not ready to publish: 2 issues found.');
});

it('carries every issue into its refusal data', function (): void {
    $issues = [PublishIssue::of('a', 'A'), PublishIssue::of('b', 'B')];

    try {
        ConfiguratorPublishRefused::ifAny($issues);
    } catch (ConfiguratorPublishRefused $refused) {
        expect($refused->issues)->toBe($issues)
            ->and($refused->refusalData())->toBe([
                'issues' => [
                    ['code' => 'a', 'message' => 'A'],
                    ['code' => 'b', 'message' => 'B'],
                ],
            ]);
    }
});
