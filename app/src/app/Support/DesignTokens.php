<?php

declare(strict_types=1);

namespace App\Support;

/**
 * The reading side of `config/theme.php`: hands the token registry to the
 * design-system page and renders it as the CSS custom properties every
 * layout emits through `<x-theme-css />`. Tailwind utilities reference
 * those properties (see `resources/css/app.css`), so this class is the
 * only place theme values become CSS.
 */
final class DesignTokens
{
    private function __construct() {} // @codeCoverageIgnore

    /**
     * @return array<string, array{light: string, dark: string, group: string, role: string}>
     */
    public static function colors(): array
    {
        return config('theme.colors');
    }

    /**
     * The colors of one group, for the design system's chip strips.
     *
     * @return array<string, array{light: string, dark: string, group: string, role: string}>
     */
    public static function colorGroup(string $group): array
    {
        return array_filter(self::colors(), fn (array $token): bool => $token['group'] === $group);
    }

    /**
     * One color's value in one mode, for contrast arithmetic.
     */
    public static function color(string $name, string $mode): string
    {
        return $mode === 'dark' ? self::colors()[$name]['dark'] : self::colors()[$name]['light'];
    }

    /**
     * @return array{display: string, body: string}
     */
    public static function fonts(): array
    {
        return config('theme.fonts');
    }

    /**
     * @return array{card: string, field: string}
     */
    public static function radii(): array
    {
        return config('theme.radii');
    }

    public static function themeName(): string
    {
        return config('theme.name');
    }

    /**
     * The stylesheet body `<x-theme-css />` inlines: every token as a
     * `--ui-*` custom property, light values on `:root`, dark values
     * scoped to the `supports-dark` opt-in the layouts already use.
     */
    public static function css(): string
    {
        $light = [];
        $dark = [];

        foreach (self::colors() as $name => $token) {
            $light[] = "--ui-{$name}: {$token['light']};";
            $dark[] = "--ui-{$name}: {$token['dark']};";
        }

        foreach (self::fonts() as $name => $stack) {
            $light[] = "--ui-font-{$name}: {$stack};";
        }

        foreach (self::radii() as $name => $radius) {
            $light[] = "--ui-radius-{$name}: {$radius};";
        }

        return ':root { '.implode(' ', $light).' } '
            .'@media (prefers-color-scheme: dark) { .supports-dark { color-scheme: dark; '.implode(' ', $dark).' } }';
    }
}
