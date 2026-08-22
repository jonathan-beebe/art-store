<?php

namespace App\Support;

use PHPUnit\Framework\TestCase;

class PlaceholderImageTest extends TestCase
{
    public function test_same_title_renders_the_same_svg(): void
    {
        $this->assertSame(PlaceholderImage::svg('Blue Heron'), PlaceholderImage::svg('Blue Heron'));
    }

    public function test_different_titles_render_different_svgs(): void
    {
        $this->assertNotSame(PlaceholderImage::svg('Blue Heron'), PlaceholderImage::svg('Red Fox'));
    }

    public function test_svg_carries_the_title_as_an_accessible_label(): void
    {
        $svg = PlaceholderImage::svg('Mug & Bowl');

        $this->assertStringContainsString('aria-label="Mug &amp; Bowl"', $svg);
        $this->assertStringStartsWith('<svg', $svg);
    }

    public function test_data_uri_is_base64_svg(): void
    {
        $uri = PlaceholderImage::dataUri('Blue Heron');

        $this->assertStringStartsWith('data:image/svg+xml;base64,', $uri);
        $this->assertSame(PlaceholderImage::svg('Blue Heron'), base64_decode(substr($uri, strlen('data:image/svg+xml;base64,'))));
    }
}
