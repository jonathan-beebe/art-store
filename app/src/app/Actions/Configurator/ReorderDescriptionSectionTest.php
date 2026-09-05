<?php

declare(strict_types=1);

namespace App\Actions\Configurator;

use App\Domain\Configurator\DescriptionSectionMove;
use App\Models\DescriptionSection;

it('swaps a section with the one before it', function (): void {
    $listing = $this->listing($this->seller());
    $first = DescriptionSection::factory()->create(['listing_id' => $listing->id, 'position' => 0]);
    $second = DescriptionSection::factory()->create(['listing_id' => $listing->id, 'position' => 1]);

    app(ReorderDescriptionSection::class)($second, DescriptionSectionMove::Up);

    expect($second->fresh()?->position)->toBe(0)
        ->and($first->fresh()?->position)->toBe(1);
});

it('swaps a section with the one after it', function (): void {
    $listing = $this->listing($this->seller());
    $first = DescriptionSection::factory()->create(['listing_id' => $listing->id, 'position' => 0]);
    $second = DescriptionSection::factory()->create(['listing_id' => $listing->id, 'position' => 1]);

    app(ReorderDescriptionSection::class)($first, DescriptionSectionMove::Down);

    expect($first->fresh()?->position)->toBe(1)
        ->and($second->fresh()?->position)->toBe(0);
});

it('does nothing moving the first section up, or the last section down', function (): void {
    $listing = $this->listing($this->seller());
    $only = DescriptionSection::factory()->create(['listing_id' => $listing->id, 'position' => 0]);

    app(ReorderDescriptionSection::class)($only, DescriptionSectionMove::Up);
    app(ReorderDescriptionSection::class)($only, DescriptionSectionMove::Down);

    expect($only->fresh()?->position)->toBe(0);
});
