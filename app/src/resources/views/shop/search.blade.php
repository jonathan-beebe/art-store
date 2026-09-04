<x-layouts.shop title="Search — Art Store">
    <h1 class="max-w-2xl font-display text-4xl leading-tight text-ink">
        @if ($search->hasTerm())
            Art matching “{{ $search->term }}”
        @else
            Search the shop
        @endif
    </h1>

    @if ($listings === null)
        <p class="mt-16 text-lg text-ink-muted">Search for a maker, material, or piece to see what's for sale.</p>
    @else
        @include('shop.partials.listing-grid', ['listings' => $listings])
    @endif
</x-layouts.shop>
