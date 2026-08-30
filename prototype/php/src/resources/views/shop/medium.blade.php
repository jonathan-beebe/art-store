<x-layouts.shop :title="$label.' — Art Store'">
    <h1 class="max-w-2xl font-display text-4xl leading-tight text-ink">{{ $label }}</h1>

    <div class="mt-6 flex items-center gap-2 sm:hidden">
        @include('shop.partials.media-gallery-panel', ['browse' => $browse, 'activeMedium' => $medium])
        @if ($browse !== [])
            <x-ui.chip :active="true">{{ $label }}</x-ui.chip>
        @endif
    </div>
    <div class="mt-8 hidden sm:block">
        @include('shop.partials.media-tile-row', ['browse' => $browse, 'activeMedium' => $medium, 'variant' => 'photo'])
    </div>

    @include('shop.partials.listing-grid', ['listings' => $listings])
</x-layouts.shop>
