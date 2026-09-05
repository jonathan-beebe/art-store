<?php

declare(strict_types=1);

namespace App\Actions\Configurator;

use App\Domain\Configurator\DescriptionSectionKind;
use App\Models\DescriptionSection;

it('updates a section’s kind, title, and markdown body', function (): void {
    $section = DescriptionSection::factory()->create(['kind' => DescriptionSectionKind::Text]);

    $updated = app(UpdateDescriptionSection::class)($section, DescriptionSectionKind::Care, 'Care', 'Hand wash only.', null);

    expect($updated->kind)->toBe(DescriptionSectionKind::Care)
        ->and($updated->title)->toBe('Care')
        ->and($updated->body_md)->toBe('Hand wash only.')
        ->and($updated->body_json)->toBeNull();
});

it('updates a section to a json body', function (): void {
    $section = DescriptionSection::factory()->create(['kind' => DescriptionSectionKind::Text, 'body_md' => 'Old text']);
    $rows = [['label' => 'Height', 'value' => '10 in']];

    $updated = app(UpdateDescriptionSection::class)($section, DescriptionSectionKind::Specs, null, null, $rows);

    expect($updated->kind)->toBe(DescriptionSectionKind::Specs)
        ->and($updated->body_json)->toBe($rows)
        ->and($updated->body_md)->toBeNull();
});
