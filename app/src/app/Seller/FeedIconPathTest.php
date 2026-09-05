<?php

declare(strict_types=1);

namespace App\Seller;

use App\Domain\Seller\FeedIcon;

it('gives every case a non-empty path, and no two cases the same one', function (): void {
    $paths = array_map(fn (FeedIcon $icon): string => FeedIconPath::of($icon), FeedIcon::cases());

    foreach ($paths as $path) {
        expect($path)->not->toBe('');
    }

    expect(array_values(array_unique($paths)))->toHaveCount(count($paths));
});

it('draws the eye and truck glyphs the seller chrome expects', function (): void {
    expect(FeedIconPath::of(FeedIcon::Eye))->toStartWith('M2.036 12.322')
        ->and(FeedIconPath::of(FeedIcon::Truck))->toStartWith('M8.25 18.75');
});
