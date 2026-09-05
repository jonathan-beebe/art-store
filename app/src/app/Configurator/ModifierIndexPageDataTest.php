<?php

declare(strict_types=1);

namespace App\Configurator;

use App\Domain\Configurator\ModifierKind;
use App\Models\Modifier;

it('resolves a query-string kind to the enum case', function (): void {
    $listing = test()->listing(test()->seller());

    $data = ModifierIndexPageData::build($listing, 'measurement');

    expect($data['addKind'])->toBe(ModifierKind::Measurement);
});

it('leaves the add kind null with no query string or an unknown one', function (?string $raw): void {
    $listing = test()->listing(test()->seller());

    $data = ModifierIndexPageData::build($listing, $raw);

    expect($data['addKind'])->toBeNull();
})->with(['absent' => [null], 'unknown' => ['nonsense']]);

it('lists the listing’s questions and choices', function (): void {
    $listing = test()->listing(test()->seller());
    Modifier::factory()->create(['listing_id' => $listing->id, 'prompt' => 'A question']);

    $data = ModifierIndexPageData::build($listing);

    /** @var \Illuminate\Database\Eloquent\Collection<int, Modifier> $modifiers */
    $modifiers = $data['modifiers'];

    expect($modifiers->pluck('prompt')->all())->toBe(['A question'])
        ->and($data['axes'])->toHaveCount(0)
        ->and($data['preview'])->toBeNull();
});
