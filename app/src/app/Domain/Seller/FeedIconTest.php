<?php

declare(strict_types=1);

namespace App\Domain\Seller;

it('gives every case a non-empty path, and no two cases the same one', function (): void {
    $paths = array_map(fn (FeedIcon $icon): string => $icon->path(), FeedIcon::cases());

    foreach ($paths as $path) {
        expect($path)->not->toBe('');
    }

    expect(array_values(array_unique($paths)))->toHaveCount(count($paths));
});
