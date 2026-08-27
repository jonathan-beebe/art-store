<?php

declare(strict_types=1);

namespace App\Domain\Configurator;

function passingVariant(): VariantSnapshot
{
    return new VariantSnapshot('vrt_01', true, 1000, false, 0, ['axs_01']);
}

it('passes a clean configuration with nothing to say', function (): void {
    $issues = ConfiguratorPublishValidation::check(
        axisIds: ['axs_01'],
        optionCountsPerAxis: [3],
        variants: [passingVariant()],
        modifierCount: 1,
        quantityBreakCount: 1,
        sectionCount: 1,
    );

    expect($issues)->toBe([]);
});

it('flags an enabled variant priced below zero', function (): void {
    $variant = new VariantSnapshot('vrt_01', true, -100, false, 0, ['axs_01']);

    $issues = ConfiguratorPublishValidation::check(['axs_01'], [1], [$variant], 0, 0, 0);

    expect($issues)->toHaveCount(1)
        ->and($issues[0]->code)->toBe('variant_priced_negative');
});

it('does not flag a disabled variant priced below zero', function (): void {
    $variant = new VariantSnapshot('vrt_01', false, -100, false, 0, []);

    expect(ConfiguratorPublishValidation::check(['axs_01'], [1], [$variant], 0, 0, 0))->toBe([]);
});

it('flags a variant missing a value for one of the listing’s axes', function (): void {
    $variant = new VariantSnapshot('vrt_01', true, 1000, false, 0, ['axs_01']);

    $issues = ConfiguratorPublishValidation::check(['axs_01', 'axs_02'], [1, 1], [$variant], 0, 0, 0);

    expect($issues)->toHaveCount(1)
        ->and($issues[0]->code)->toBe('variant_missing_axis_value');
});

it('flags a serialized variant with no available unit', function (): void {
    $variant = new VariantSnapshot('vrt_01', true, 1000, true, 0, []);

    $issues = ConfiguratorPublishValidation::check([], [], [$variant], 0, 0, 0);

    expect($issues)->toHaveCount(1)
        ->and($issues[0]->code)->toBe('serialized_variant_has_no_units');
});

it('does not flag a serialized variant with an available unit', function (): void {
    $variant = new VariantSnapshot('vrt_01', true, 1000, true, 1, []);

    expect(ConfiguratorPublishValidation::check([], [], [$variant], 0, 0, 0))->toBe([]);
});

it('flags an axis with too many options', function (): void {
    $issues = ConfiguratorPublishValidation::check([], [ConfiguratorPublishValidation::MAX_OPTIONS_PER_AXIS + 1], [], 0, 0, 0);

    expect($issues)->toHaveCount(1)
        ->and($issues[0]->code)->toBe('axis_too_many_options');
});

it('flags too many variants, modifiers, quantity tiers, and sections', function (): void {
    $variants = array_fill(0, ConfiguratorPublishValidation::MAX_VARIANTS + 1, passingVariant());

    $issues = ConfiguratorPublishValidation::check(
        ['axs_01'],
        [1],
        $variants,
        ConfiguratorPublishValidation::MAX_MODIFIERS + 1,
        ConfiguratorPublishValidation::MAX_QUANTITY_TIERS + 1,
        ConfiguratorPublishValidation::MAX_SECTIONS + 1,
    );

    expect(array_map(fn (PublishIssue $issue): string => $issue->code, $issues))->toBe([
        'too_many_variants',
        'too_many_modifiers',
        'too_many_quantity_tiers',
        'too_many_sections',
    ]);
});
