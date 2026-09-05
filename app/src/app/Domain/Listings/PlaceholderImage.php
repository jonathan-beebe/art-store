<?php

declare(strict_types=1);

namespace App\Domain\Listings;

/**
 * Stand-in artwork for listings without an uploaded image. The palette and
 * composition derive from the title so the same listing always renders the
 * same picture and different listings look different.
 */
final class PlaceholderImage
{
    private const WIDTH = 800;

    private const HEIGHT = 800;

    private function __construct() {} // @codeCoverageIgnore

    public static function svg(string $title): string
    {
        $seed = crc32($title);
        $hue = $seed % 360;
        $secondHue = ($hue + 140 + ($seed >> 8) % 80) % 360;
        $shapes = self::shapes($seed, $hue, $secondHue);
        $label = htmlspecialchars(mb_substr($title, 0, 40), ENT_QUOTES | ENT_XML1, 'UTF-8');

        return <<<SVG
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 800 800" width="800" height="800" role="img" aria-label="{$label}">
        <defs><linearGradient id="g" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="hsl({$hue} 60% 88%)"/><stop offset="1" stop-color="hsl({$secondHue} 55% 80%)"/></linearGradient></defs>
        <rect width="800" height="800" fill="url(#g)"/>
        {$shapes}
        <text x="40" y="760" font-family="ui-sans-serif, system-ui, sans-serif" font-size="28" fill="hsl({$hue} 40% 25%)">{$label}</text>
        </svg>
        SVG;
    }

    public static function dataUri(string $title): string
    {
        return 'data:image/svg+xml;base64,'.base64_encode(self::svg($title));
    }

    private static function shapes(int $seed, int $hue, int $secondHue): string
    {
        $shapes = '';
        $count = 3 + $seed % 4;
        for ($index = 0; $index < $count; $index++) {
            $step = ($seed >> ($index * 3)) & 0xFFFF;
            $x = 100 + ($step * 7) % (self::WIDTH - 200);
            $y = 100 + ($step * 13) % (self::HEIGHT - 300);
            $size = 80 + ($step * 3) % 220;
            $fillHue = $index % 2 === 0 ? $hue : $secondHue;
            $shapes .= $index % 3 === 0
                ? "<circle cx=\"{$x}\" cy=\"{$y}\" r=\"{$size}\" fill=\"hsl({$fillHue} 55% 55% / 0.45)\"/>"
                : "<rect x=\"{$x}\" y=\"{$y}\" width=\"{$size}\" height=\"{$size}\" rx=\"24\" fill=\"hsl({$fillHue} 50% 50% / 0.4)\"/>";
        }

        return $shapes;
    }
}
