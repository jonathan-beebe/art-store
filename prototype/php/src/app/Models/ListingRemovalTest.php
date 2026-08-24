<?php

declare(strict_types=1);

namespace App\Models;

it('is active while it carries no lifted_at', function (): void {
    $removal = ListingRemoval::factory()->make();

    expect($removal->isActive())->toBeTrue();
});

it('is not active once lifted', function (): void {
    $removal = ListingRemoval::factory()->lifted()->make();

    expect($removal->isActive())->toBeFalse();
});

it('records when it was lifted', function (): void {
    $removal = ListingRemoval::factory()->create();

    $removal->lift($this->moment('2026-08-23 10:00:00'));

    expect($removal->isActive())->toBeFalse()
        ->and($removal->fresh()?->lifted_at?->format('Y-m-d H:i:s'))->toBe('2026-08-23 10:00:00');
});

it('belongs to the listing it removes', function (): void {
    $listing = $this->listing($this->seller());
    $removal = ListingRemoval::factory()->create(['listing_id' => $listing->id]);

    expect($removal->listing->id)->toBe($listing->id);
});
