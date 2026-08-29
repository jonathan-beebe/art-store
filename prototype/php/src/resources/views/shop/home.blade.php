<x-layouts.shop title="Art Store">
    <h1 class="max-w-2xl font-display text-4xl leading-tight text-ink">
        @if ($search->hasTerm())
            Art matching “{{ $search->term }}”
        @else
            Hand-made art, straight from the artist
        @endif
    </h1>

    <div class="mt-8">
        @include('shop.partials.category-tiles', [
            'media' => $media,
            'activeMedium' => $search->medium,
            'term' => $search->hasTerm() ? $search->term : null,
        ])
    </div>

    @if ($listings->isEmpty())
        <p class="mt-16 text-lg text-ink-muted">No art matches that yet.</p>
    @else
        <p class="mt-10 text-sm text-ink-faint">{{ $listings->total() }} {{ str('work')->plural($listings->total()) }}</p>

        <ul class="mt-4 grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ($listings as $listing)
                <li><x-listing-card :listing="$listing" /></li>
            @endforeach
        </ul>

        <div class="mt-16">{{ $listings->links() }}</div>
    @endif
</x-layouts.shop>
