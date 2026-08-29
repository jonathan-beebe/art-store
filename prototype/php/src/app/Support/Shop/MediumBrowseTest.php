<?php

declare(strict_types=1);

use App\Models\Favorite;
use App\Support\Shop\MediumBrowse;

it('offers nothing before any medium is attributed', function (): void {
    expect(MediumBrowse::forStorefront())->toBe([]);
});

it('counts each medium and covers it with its most-favorited listing image', function (): void {
    $seller = $this->seller();
    $plain = $this->listing($seller);
    $favored = $this->listing($seller);
    $this->mediumAttribute($plain, 'Oil');
    $this->mediumAttribute($favored, 'Oil');
    $this->listingImage($favored);
    Favorite::factory()->create(['listing_id' => $favored->id]);

    $browse = MediumBrowse::forStorefront();

    expect($browse)->toHaveCount(1)
        ->and($browse[0]['value'])->toBe('oil')
        ->and($browse[0]['label'])->toBe('Oil')
        ->and($browse[0]['count'])->toBe(2)
        ->and($browse[0]['coverUrl'])->toBe($favored->imageUrl());
});

it('falls back to the newest listing when nothing is favorited', function (): void {
    $seller = $this->seller();
    $older = $this->listing($seller, ['created_at' => moment('2026-08-01 10:00:00')]);
    $newer = $this->listing($seller, ['created_at' => moment('2026-08-20 10:00:00')]);
    $this->mediumAttribute($older, 'Ceramic');
    $this->mediumAttribute($newer, 'Ceramic');
    $this->listingImage($newer);

    $browse = MediumBrowse::forStorefront();

    expect($browse[0]['coverUrl'])->toBe($newer->imageUrl());
});
