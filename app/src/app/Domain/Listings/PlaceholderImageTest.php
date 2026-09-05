<?php

declare(strict_types=1);

namespace App\Domain\Listings;

it('renders the same svg for the same title', function (): void {
    expect(PlaceholderImage::svg('Blue Heron'))->toBe(PlaceholderImage::svg('Blue Heron'));
});

it('renders different svgs for different titles', function (): void {
    expect(PlaceholderImage::svg('Blue Heron'))->not->toBe(PlaceholderImage::svg('Red Fox'));
});

it('carries the title as an accessible label', function (): void {
    $svg = PlaceholderImage::svg('Mug & Bowl');

    expect($svg)->toContain('aria-label="Mug &amp; Bowl"')
        ->and($svg)->toStartWith('<svg');
});

it('encodes the data uri as base64 svg', function (): void {
    $uri = PlaceholderImage::dataUri('Blue Heron');

    expect($uri)->toStartWith('data:image/svg+xml;base64,')
        ->and(base64_decode(substr($uri, strlen('data:image/svg+xml;base64,')), true))
        ->toBe(PlaceholderImage::svg('Blue Heron'));
});
