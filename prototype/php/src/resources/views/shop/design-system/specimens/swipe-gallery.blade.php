<x-specimen-layout title="Swipe gallery — specimen">
    <x-ui.alert tone="notice" class="m-4">Exploration — not shipped. The listing page shows a cover photo and thumbnail grid; this swipe carousel stays here as an unshipped alternative.</x-ui.alert>

    @php
        $listing = $listings->first();
        $images = $listing?->images ?? collect();
    @endphp

    @if ($listing === null)
        <p class="p-4 text-sm text-ink-muted">No for-sale listing yet — seed the catalog to see the gallery.</p>
    @else
        <article class="flex flex-col gap-3 py-4">
            {{-- The mobile-only piece: photos as a full-width scroll-snap
                 carousel — one flick per photo — where desktop shows a
                 thumbnail row instead. --}}
            @if ($images->count() > 1)
                <div class="flex snap-x snap-mandatory gap-2 overflow-x-auto px-4">
                    @foreach ($images as $image)
                        <img src="{{ $image->url() }}" alt="{{ $listing->title }} — photo {{ $loop->iteration }}"
                             class="aspect-square w-[88%] shrink-0 snap-center rounded-card object-cover">
                    @endforeach
                </div>
                <div class="flex justify-center gap-1.5" aria-hidden="true">
                    @foreach ($images as $image)
                        <span class="size-1.5 rounded-full {{ $loop->first ? 'bg-ink' : 'bg-line-strong' }}"></span>
                    @endforeach
                </div>
            @else
                <div class="px-4">
                    <img src="{{ $listing->imageUrl() }}" alt="{{ $listing->title }}" class="aspect-square w-full rounded-card object-cover">
                    <p class="mt-2 text-xs text-ink-faint">One photo on this listing — add more and they swipe.</p>
                </div>
            @endif

            <div class="px-4">
                <h1 class="font-display text-2xl leading-tight text-ink">{{ $listing->title }}</h1>
                <p class="mt-1 flex items-center gap-2 text-sm text-ink-faint">
                    <x-ui.avatar :name="$listing->seller->displayName()" size="xs" />
                    {{ $listing->seller->displayName() }}
                </p>
                <p class="mt-3 text-xl text-ink">{{ $listing->price()->format() }}</p>
            </div>
        </article>
    @endif
</x-specimen-layout>
