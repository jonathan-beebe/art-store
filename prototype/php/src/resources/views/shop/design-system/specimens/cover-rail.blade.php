<x-specimen-layout title="Cover rail — specimen">
    <div class="flex flex-col gap-4 py-4">
        <x-ui.alert tone="notice" class="mx-4">Exploration — not shipped. The shipped mobile browse pattern is the media-gallery-panel bottom sheet (see the Browse sheet specimen); this cover-card rail stays here as an unshipped alternative.</x-ui.alert>

        <h1 class="px-4 font-display text-2xl leading-tight text-ink">Hand-made art, straight from the artist</h1>

        @if ($browse === [])
            <p class="px-4 text-sm text-ink-muted">No attributed medium yet — seed the catalog to fill the rail.</p>
        @else
            <div class="px-4">
                @include('shop.partials.media-cover-rail', ['browse' => $browse, 'activeMedium' => null])
            </div>
        @endif

        <ul class="grid grid-cols-2 gap-3 px-4">
            @foreach ($listings as $listing)
                <li><x-listing-card :listing="$listing" /></li>
            @endforeach
        </ul>
    </div>
</x-specimen-layout>
