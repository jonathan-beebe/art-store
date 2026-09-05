<?php

declare(strict_types=1);

namespace App\Support\Configurator;

use App\Domain\Configurator\PublishIssue;

it('lists one section per configurator page, in the order the hub lists them', function (): void {
    $labels = array_column(ConfiguratorSectionNav::sections(), 'label');

    expect($labels)->toBe([
        'Basics',
        'Photos',
        'Pricing & options',
        'Combinations',
        'Questions',
        'Quantity discounts',
        'Description sections',
        'FAQs',
    ]);
});

it('flags a section whose issue codes match one of the listing\'s current issues', function (): void {
    $issues = [PublishIssue::of('option_missing_price', 'irrelevant', 'ov_1')];

    expect(ConfiguratorSectionNav::hasIssue($issues, ['option_missing_price', 'axis_too_many_options']))->toBeTrue();
});

it('never flags a section that carries no issue codes, no matter what issues exist', function (): void {
    $issues = [PublishIssue::of('too_many_modifiers', 'irrelevant')];

    expect(ConfiguratorSectionNav::hasIssue($issues, []))->toBeFalse();
});

it('does not flag a section whose issue codes match none of the current issues', function (): void {
    $issues = [PublishIssue::of('too_many_modifiers', 'irrelevant')];

    expect(ConfiguratorSectionNav::hasIssue($issues, ['option_missing_price']))->toBeFalse();
});
