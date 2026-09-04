<?php

declare(strict_types=1);

namespace App\Actions\Configurator;

use App\Models\DescriptionSection;

it('deletes a description section', function (): void {
    $section = DescriptionSection::factory()->create();

    app(DeleteDescriptionSection::class)($section);

    expect(DescriptionSection::find($section->id))->toBeNull();
});
