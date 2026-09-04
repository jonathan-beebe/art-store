<?php

declare(strict_types=1);

namespace App\Actions\Configurator;

use App\Domain\Configurator\DescriptionSectionKind;

it('adds a markdown section', function (): void {
    $listing = $this->listing($this->seller());

    $section = app(AddDescriptionSection::class)($listing, 0, DescriptionSectionKind::Care, 'Care', 'Hand wash only.');

    expect($section->listing_id)->toBe($listing->id)
        ->and($section->kind)->toBe(DescriptionSectionKind::Care)
        ->and($section->title)->toBe('Care')
        ->and($section->body_md)->toBe('Hand wash only.')
        ->and($section->body_json)->toBeNull();
});

it('adds a json-bodied section', function (): void {
    $faqs = [['q' => 'Ships when?', 'a' => 'In 3 days.']];

    $section = app(AddDescriptionSection::class)($this->listing($this->seller()), 1, DescriptionSectionKind::Faq, 'FAQ', null, $faqs);

    expect($section->kind)->toBe(DescriptionSectionKind::Faq)
        ->and($section->body_json)->toBe($faqs)
        ->and($section->body_md)->toBeNull();
});
