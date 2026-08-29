<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Configurator\DescriptionSectionKind;

it('belongs to its listing and casts its kind', function (): void {
    $listing = $this->listing($this->seller());
    $section = DescriptionSection::factory()->create(['listing_id' => $listing->id]);

    expect($section->listing()->first()?->id)->toBe($listing->id)
        ->and($section->kind)->toBe(DescriptionSectionKind::Text);
});

it('casts a json body for the typed sections that use one', function (): void {
    $faqs = [['q' => 'Ships when?', 'a' => 'In 3 days.']];
    $section = DescriptionSection::factory()->json(DescriptionSectionKind::Faq, $faqs)->create();

    expect($section->kind)->toBe(DescriptionSectionKind::Faq)
        ->and($section->body_json)->toBe($faqs)
        ->and($section->body_md)->toBeNull();
});
