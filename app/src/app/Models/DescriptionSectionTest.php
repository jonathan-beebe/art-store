<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Configurator\DescriptionSectionKind;

it('stores the faq kind\'s body as json and leaves body_md null', function (): void {
    $faqs = [['q' => 'Ships when?', 'a' => 'In 3 days.']];
    $section = DescriptionSection::factory()->json(DescriptionSectionKind::Faq, $faqs)->create();

    expect($section->kind)->toBe(DescriptionSectionKind::Faq)
        ->and($section->body_json)->toBe($faqs)
        ->and($section->body_md)->toBeNull();
});
