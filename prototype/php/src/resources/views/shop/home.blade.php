<x-layouts.shop title="Art Store">
    <h1 class="max-w-2xl font-display text-4xl leading-tight text-ink">
        @if ($search->hasTerm())
            Art matching “{{ $search->term }}”
        @else
            Hand-made art, straight from the artist
        @endif
    </h1>

    @php
        $term = $search->hasTerm() ? $search->term : null;
        $activeLabel = collect($browse)->firstWhere('value', $search->medium)['label'] ?? null;
    @endphp

    {{-- Under 640px the picker is the browse-media pill and its bottom
         sheet; from sm: up it is the cover-card row with the drawer. --}}
    <div class="mt-6 flex items-center gap-2 sm:hidden">
        @include('shop.partials.media-gallery-panel', ['browse' => $browse, 'activeMedium' => $search->medium, 'term' => $term])
        @if ($browse !== [])
            <x-ui.chip :active="true">{{ $activeLabel ?? 'All art' }}</x-ui.chip>
        @endif
    </div>
    <div class="mt-8 hidden sm:block">
        @include('shop.partials.media-tile-row', ['browse' => $browse, 'activeMedium' => $search->medium, 'term' => $term, 'variant' => 'photo'])
    </div>

    @if ($listings->isEmpty())
        <p class="mt-16 text-lg text-ink-muted">No art matches that yet.</p>
    @else
        <p class="mt-10 text-sm text-ink-faint">{{ $listings->total() }} {{ str('work')->plural($listings->total()) }}</p>

        <ul class="mt-4 grid grid-cols-2 gap-4 sm:gap-6 lg:grid-cols-3">
            @foreach ($listings as $listing)
                <li><x-listing-card :listing="$listing" /></li>
            @endforeach
        </ul>

        <div class="mt-16">{{ $listings->links() }}</div>
    @endif
</x-layouts.shop>
