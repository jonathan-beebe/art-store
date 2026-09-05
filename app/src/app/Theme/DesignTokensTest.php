<?php

declare(strict_types=1);

use App\Domain\Theme\Contrast;
use App\Theme\DesignTokens;

it('carries every color as light, dark, group, and role', function (): void {
    foreach (DesignTokens::colors() as $name => $token) {
        expect($token['light'])->toMatch('/^#[0-9a-f]{6}$/')
            ->and($token['dark'])->toMatch('/^#[0-9a-f]{6}$/')
            ->and($token['group'])->toBeIn(['neutral', 'accent', 'status', 'tint'])
            ->and($token['role'])->not->toBe('');
    }
});

it('filters a chip strip down to its group', function (): void {
    $tints = DesignTokens::colorGroup('tint');

    expect($tints)->toHaveKeys(['tint-1', 'tint-2', 'tint-3', 'tint-4', 'tint-5', 'on-tint'])
        ->and(array_column($tints, 'group'))->each->toBe('tint');
});

it('reads one color per mode', function (): void {
    expect(DesignTokens::color('canvas', 'light'))->toBe('#f6efe4')
        ->and(DesignTokens::color('canvas', 'dark'))->toBe('#221a14');
});

it('names the theme and its font and radius scales', function (): void {
    expect(DesignTokens::themeName())->toBe('Warm Craft')
        ->and(DesignTokens::fonts()['display'])->toContain('Young Serif')
        ->and(DesignTokens::fonts()['body'])->toContain('Karla')
        ->and(DesignTokens::radii())->toHaveKeys(['card', 'field']);
});

it('renders light tokens on :root and dark tokens behind the supports-dark opt-in', function (): void {
    $css = DesignTokens::css();

    expect($css)->toContain(':root { --ui-canvas: #f6efe4;')
        ->toContain("--ui-font-display: 'Young Serif', 'Iowan Old Style', Georgia, serif;")
        ->toContain('--ui-radius-card: 1rem;')
        ->toContain('@media (prefers-color-scheme: dark) { .supports-dark { color-scheme: dark; --ui-canvas: #221a14;');
});

it('keeps body text readable on its backgrounds in both modes — the palette self-checks', function (): void {
    $pairings = [
        ['ink', 'canvas'], ['ink', 'surface'],
        ['ink-muted', 'canvas'], ['ink-faint', 'canvas'],
        ['on-accent', 'accent'], ['accent', 'canvas'],
        ['danger', 'danger-surface'], ['success', 'success-surface'], ['notice', 'notice-surface'],
    ];

    foreach (['light', 'dark'] as $mode) {
        foreach ($pairings as [$fg, $bg]) {
            $ratio = Contrast::ratio(DesignTokens::color($fg, $mode), DesignTokens::color($bg, $mode));

            expect(Contrast::meetsAa($ratio))->toBeTrue("{$fg} on {$bg} reads {$ratio} in {$mode}");
        }
    }
});
