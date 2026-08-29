<x-layouts.shop title="Favorites — Art Store">
    <h1 class="font-display text-4xl leading-tight text-ink">Favorites</h1>

    @if ($listings->isEmpty())
        <p class="mt-10 text-lg text-ink-muted">Nothing saved yet. Tap Favorite on any piece you want to come back to.</p>
    @else
        <ul class="mt-14 grid grid-cols-1 gap-x-8 gap-y-14 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ($listings as $listing)
                <li><x-listing-card :listing="$listing" /></li>
            @endforeach
        </ul>
    @endif
</x-layouts.shop>
