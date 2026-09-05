<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Design tokens — theme "Warm Craft"
|--------------------------------------------------------------------------
|
| The single source of truth for the storefront's visual language. Every
| color is a semantic role: storefront views only ever say `bg-canvas` or
| `text-ink-muted`, and this file decides what those mean — in light and
| in dark. Seller and admin views style
| themselves in raw Tailwind and stay outside this contract. `App\Support\DesignTokens`
| turns this file into the CSS custom properties every layout emits
| (`<x-theme-css />`), and `resources/css/app.css` maps Tailwind utilities
| onto those properties, so retheming the storefront is editing this file
| and nothing else.
|
| `/design-system` renders this same registry as paint chips, pairings,
| and specimens, so the page can never drift from the shipped CSS.
|
*/

return [

    'name' => 'Warm Craft',

    /*
    | Font stacks. `display` carries headings, card titles, and the
    | wordmark; `body` carries everything else. The webfont <link> lives in
    | the shop layout; the fallbacks here are what any layout without it
    | (seller, admin) renders.
    */
    'fonts' => [
        'display' => "'Young Serif', 'Iowan Old Style', Georgia, serif",
        'body' => "'Karla', 'Avenir Next', system-ui, sans-serif",
    ],

    /*
    | Corner radii. Cards and images take `card`; text controls take
    | `field`; pills and buttons stay fully round.
    */
    'radii' => [
        'card' => '1rem',
        'field' => '0.75rem',
    ],

    /*
    | Colors: name => [light, dark, group, role]. Groups drive the
    | design-system chip strips; roles are the one-line contract for when
    | to reach for the token.
    */
    'colors' => [

        // Neutrals — the room the art hangs in.
        'canvas' => ['light' => '#f6efe4', 'dark' => '#221a14', 'group' => 'neutral', 'role' => 'Page background'],
        'surface' => ['light' => '#fffbf3', 'dark' => '#2d241c', 'group' => 'neutral', 'role' => 'Cards, panels, and text controls'],
        'ink' => ['light' => '#3d2f26', 'dark' => '#f0e6d8', 'group' => 'neutral', 'role' => 'Primary text'],
        'ink-muted' => ['light' => '#5b4a3e', 'dark' => '#c4b09c', 'group' => 'neutral', 'role' => 'Secondary text'],
        'ink-faint' => ['light' => '#6f5d4c', 'dark' => '#a08a76', 'group' => 'neutral', 'role' => 'Meta text: bylines, captions, placeholders'],
        'line' => ['light' => '#e6d9c6', 'dark' => '#3e332a', 'group' => 'neutral', 'role' => 'Hairline borders and dividers'],
        'line-strong' => ['light' => '#cdbba1', 'dark' => '#55463a', 'group' => 'neutral', 'role' => 'Control borders'],

        // Accent — terracotta, the one color the chrome may speak in.
        'accent' => ['light' => '#b04f26', 'dark' => '#d97a4a', 'group' => 'accent', 'role' => 'Primary actions, links, focus'],
        'accent-strong' => ['light' => '#8f3f1e', 'dark' => '#e08d61', 'group' => 'accent', 'role' => 'Hover and active on accent'],
        'on-accent' => ['light' => '#fffbf3', 'dark' => '#2b1c12', 'group' => 'accent', 'role' => 'Text on accent fills'],
        'accent-soft' => ['light' => '#f0e2d2', 'dark' => '#3a2b1f', 'group' => 'accent', 'role' => 'Soft accent fills: badges, active rows'],

        // Status — warm-leaning so they sit in the palette.
        'danger' => ['light' => '#9e3b22', 'dark' => '#e0846c', 'group' => 'status', 'role' => 'Errors and destructive text'],
        'danger-surface' => ['light' => '#f7e2da', 'dark' => '#40241b', 'group' => 'status', 'role' => 'Error panel fill'],
        'danger-line' => ['light' => '#e5bdad', 'dark' => '#5e392c', 'group' => 'status', 'role' => 'Error panel border'],
        'success' => ['light' => '#4d6b3c', 'dark' => '#a4bf8a', 'group' => 'status', 'role' => 'Confirmations'],
        'success-surface' => ['light' => '#e8eddc', 'dark' => '#2a3220', 'group' => 'status', 'role' => 'Confirmation panel fill'],
        'success-line' => ['light' => '#c2d1a8', 'dark' => '#46543a', 'group' => 'status', 'role' => 'Confirmation panel border'],
        'notice' => ['light' => '#8a5a1b', 'dark' => '#d9a85e', 'group' => 'status', 'role' => 'Warnings and holds'],
        'notice-surface' => ['light' => '#f5e8d0', 'dark' => '#3b2d18', 'group' => 'status', 'role' => 'Warning panel fill'],
        'notice-line' => ['light' => '#e2c895', 'dark' => '#5c4726', 'group' => 'status', 'role' => 'Warning panel border'],

        // Tints — object colors for category tiles and avatars. The same
        // in both modes: they color these objects, never the app's chrome.
        'tint-1' => ['light' => '#d99a6c', 'dark' => '#d99a6c', 'group' => 'tint', 'role' => 'Category and avatar fill'],
        'tint-2' => ['light' => '#c9b28a', 'dark' => '#c9b28a', 'group' => 'tint', 'role' => 'Category and avatar fill'],
        'tint-3' => ['light' => '#b9895f', 'dark' => '#b9895f', 'group' => 'tint', 'role' => 'Category and avatar fill'],
        'tint-4' => ['light' => '#a8ab7e', 'dark' => '#a8ab7e', 'group' => 'tint', 'role' => 'Category and avatar fill'],
        'tint-5' => ['light' => '#c47f5a', 'dark' => '#c47f5a', 'group' => 'tint', 'role' => 'Category and avatar fill'],
        'on-tint' => ['light' => '#3a2412', 'dark' => '#3a2412', 'group' => 'tint', 'role' => 'Text on tint fills'],
        'on-photo' => ['light' => '#fffbf3', 'dark' => '#fffbf3', 'group' => 'tint', 'role' => 'Text on photo scrims'],
        'photo-scrim' => ['light' => '#1a110c', 'dark' => '#1a110c', 'group' => 'tint', 'role' => 'Base color for the gradient behind on-photo text'],
    ],
];
