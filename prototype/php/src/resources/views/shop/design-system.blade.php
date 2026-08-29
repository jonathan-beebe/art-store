<x-layouts.shop title="Design system — Art Store">
    <div class="flex flex-wrap items-baseline gap-x-4 gap-y-2">
        <h1 class="font-display text-4xl leading-tight text-ink">Design system</h1>
        <x-ui.badge>Theme: {{ $themeName }}</x-ui.badge>
    </div>
    <p class="mt-3 max-w-2xl text-ink-muted">
        The storefront's visual language, rendered live. Every swatch, type
        specimen, atom, and component on this page is the real thing the
        shop ships — backed by the same tokens in <code class="font-mono text-sm">config/theme.php</code>,
        so a theme change lands here and on every page at once.
    </p>

    <nav aria-label="Sections" class="mt-8 flex flex-wrap gap-2">
        <x-ui.chip href="#colors">Colors</x-ui.chip>
        <x-ui.chip href="#relationships">Relationships</x-ui.chip>
        <x-ui.chip href="#typography">Typography</x-ui.chip>
        <x-ui.chip href="#atoms">Atoms</x-ui.chip>
        <x-ui.chip href="#components">Components</x-ui.chip>
        <x-ui.chip href="#mobile">Mobile</x-ui.chip>
        <x-ui.chip href="#layouts">Layouts</x-ui.chip>
    </nav>

    @include('shop.design-system.colors')
    @include('shop.design-system.relationships', ['pairings' => $pairings])
    @include('shop.design-system.typography')
    @include('shop.design-system.atoms')
    @include('shop.design-system.components', [
        'browse' => $browse,
        'listings' => $listings,
        'configurable' => $configurable,
        'configuration' => $configuration,
        'focusId' => $focusId,
    ])
    @include('shop.design-system.mobile')
    @include('shop.design-system.layouts')
</x-layouts.shop>
