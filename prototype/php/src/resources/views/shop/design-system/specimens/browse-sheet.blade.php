<x-specimen-layout title="Browse sheet — specimen">
    <div class="flex flex-col gap-4 p-4">
        <h1 class="font-display text-2xl leading-tight text-ink">Hand-made art, straight from the artist</h1>

        @if ($browse === [])
            <p class="text-sm text-ink-muted">No attributed medium yet — seed the catalog to open the sheet.</p>
        @else
            @include('shop.partials.media-gallery-panel', ['browse' => $browse, 'activeMedium' => null, 'term' => null])
        @endif

        <ul class="grid grid-cols-2 gap-3">
            @foreach ($listings as $listing)
                <li><x-listing-card :listing="$listing" /></li>
            @endforeach
        </ul>
    </div>
</x-specimen-layout>
