<x-layouts.shop title="Art Store">
    <h1 class="max-w-2xl font-display text-4xl leading-tight text-ink">
        Hand-made art, straight from the artist
    </h1>

    {{-- Under 640px the picker is the browse-media pill and its bottom
         sheet; from sm: up it is the cover-card row with the drawer. --}}
    <div class="mt-6 flex items-center gap-2 sm:hidden">
        @include('shop.partials.media-gallery-panel', ['browse' => $browse, 'activeMedium' => null])
        @if ($browse !== [])
            <x-ui.chip :active="true">All art</x-ui.chip>
        @endif
    </div>
    <div class="mt-8 hidden sm:block">
        @include('shop.partials.media-tile-row', ['browse' => $browse, 'activeMedium' => null, 'variant' => 'photo'])
    </div>

    @if ($categories !== [])
        <nav aria-label="Browse by category" class="mt-8 flex flex-wrap gap-2">
            @foreach ($categories as $entry)
                <a href="{{ route('shop.browse', ['categoryPath' => $entry['category']->browsePath()]) }}"
                   class="inline-flex items-center gap-2 rounded-full border border-line-strong bg-surface px-4 py-2 text-sm font-semibold text-ink hover:border-accent">
                    {{ $entry['category']->name }}
                    <span class="text-xs text-ink-faint">{{ $entry['count'] }}</span>
                </a>
            @endforeach
        </nav>
    @endif

    @include('shop.partials.listing-grid', ['listings' => $listings])
</x-layouts.shop>
