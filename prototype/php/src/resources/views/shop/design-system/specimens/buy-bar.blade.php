<x-specimen-layout title="Buy bar — specimen">
    @php $listing = $listings->first(); @endphp

    @if ($listing === null)
        <p class="p-4 text-sm text-ink-muted">No for-sale listing yet — seed the catalog to see the buy bar.</p>
    @else
        <article class="flex flex-col gap-4 p-4 pb-28">
            <img src="{{ $listing->imageUrl() }}" alt="{{ $listing->title }}" class="aspect-square w-full rounded-card object-cover">

            <div>
                <h1 class="font-display text-2xl leading-tight text-ink">{{ $listing->title }}</h1>
                <div class="mt-3 flex items-center gap-3 rounded-field border border-line bg-surface px-4 py-3">
                    <x-ui.avatar :name="$listing->seller->displayName()" size="sm" />
                    <p class="text-sm font-semibold text-ink">Made by {{ $listing->seller->displayName() }}</p>
                </div>
            </div>

            <p class="text-base leading-relaxed text-ink-muted">{{ $listing->description }}</p>

            <dl class="grid grid-cols-2 gap-y-3 border-y border-line py-4 text-sm">
                <dt class="text-ink-faint">Dimensions</dt>
                <dd class="text-ink">{{ $listing->dimensions ?? 'Unlisted' }}</dd>
                <dt class="text-ink-faint">Available</dt>
                <dd class="text-ink">{{ $listing->quantityLabel() }}</dd>
            </dl>
        </article>

        {{-- The mobile-only piece: price and action pinned to the viewport's
             bottom edge, so a long description or configurator never scrolls
             the buy action away. --}}
        <div class="fixed inset-x-0 bottom-0 flex items-center justify-between gap-4 border-t border-line bg-surface px-4 py-3">
            <div class="flex flex-col">
                <span class="text-lg font-semibold text-ink">{{ $listing->price()->format() }}</span>
                <span class="text-xs text-ink-faint">{{ $listing->quantityLabel() }} available</span>
            </div>
            <x-ui.button type="button" variant="primary">Add to cart</x-ui.button>
        </div>
    @endif
</x-specimen-layout>
