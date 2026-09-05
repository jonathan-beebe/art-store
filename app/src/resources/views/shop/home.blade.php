<x-layouts.shop title="Art Store">
    @if ($featured !== null)
        <x-slot:beforeMain>
            <x-featured-band :subject="$featured" />
        </x-slot:beforeMain>
    @endif

    {{-- The featured band (when present) carries the page's visual headline
         as an h2; this stays the one h1 regardless, so the outline never
         depends on whether a featured subject is configured. --}}
    <h1 class="sr-only">Art Store</h1>

    @if ($browse !== [])
        <section aria-labelledby="mediums-heading" class="mt-14">
            <x-ui.section-header title="Browse by medium" headingId="mediums-heading" />

            {{-- Under 640px the picker is the browse-media pill and its
                 bottom sheet; from sm: up it is the golden-ratio tile row
                 and its drawer. --}}
            <div class="mt-4 flex items-center gap-2 sm:hidden">
                @include('shop.partials.media-gallery-panel', ['browse' => $browse, 'activeMedium' => null])
                <x-ui.chip :active="true">All art</x-ui.chip>
            </div>
            <div class="mt-4 hidden sm:block">
                @include('shop.partials.media-tile-row', ['browse' => $browse, 'activeMedium' => null, 'variant' => 'photo'])
            </div>
        </section>
    @endif

    @if ($justListed->isNotEmpty())
        <section aria-labelledby="just-listed-heading" class="mt-14">
            <x-ui.section-header title="Just listed" headingId="just-listed-heading" />
            <ul class="mt-4 grid grid-cols-2 gap-4 sm:gap-6 lg:grid-cols-3">
                @foreach ($justListed as $listing)
                    <li><x-listing-card :listing="$listing" /></li>
                @endforeach
            </ul>
        </section>
    @endif

    @if ($categories !== [])
        <section aria-labelledby="categories-heading" class="mt-14">
            <x-ui.section-header title="Browse by category" headingId="categories-heading" />
            @php $tints = ['bg-tint-1', 'bg-tint-2', 'bg-tint-3', 'bg-tint-4', 'bg-tint-5']; @endphp
            <div class="mt-4 grid grid-cols-2 gap-4 sm:grid-cols-4">
                @foreach ($categories as $index => $entry)
                    <x-tile
                        :href="route('shop.browse', ['categoryPath' => $entry['category']->browsePath()])"
                        :label="$entry['category']->name"
                        :count="$entry['count']"
                        :cover-url="$entry['coverUrl']"
                        :tint="$tints[$index % 5]"
                    />
                @endforeach
            </div>
        </section>
    @endif

    @if ($moreListings->isNotEmpty())
        <section aria-labelledby="more-heading" class="mt-14">
            <x-ui.section-header title="More to explore" headingId="more-heading" />
            <ul class="mt-4 grid grid-cols-2 gap-4 sm:gap-6 lg:grid-cols-3">
                @foreach ($moreListings as $listing)
                    <li><x-listing-card :listing="$listing" /></li>
                @endforeach
            </ul>
        </section>
    @endif

    <x-slot:afterMain>
        <x-wayfinding-footer :browse="$browse" :categories="$categories" />
    </x-slot:afterMain>
</x-layouts.shop>
