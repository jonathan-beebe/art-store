{{--
    The Listings list pane's rows (Listings.dc.html), shared by the index
    and show views so both render the exact same list — the same idiom the
    admin listings pane holds (App\Http\Controllers\Admin\ListingController,
    x-admin.listings-cells). A 40px thumbnail (the listing's own cover image,
    or its generated placeholder — every listing has one or the other, never
    neither), a truncating title, a muted price-and-stock line, and the
    status badge, right-aligned. The selected row (a show route's own
    listing) carries a subtle fill and a 2px indigo rail on its inset left
    edge, drawn with an arbitrary box-shadow so it doesn't shift the row's
    padding the way a left border would.
--}}
@props(['listings', 'selected' => null])

<div class="flex flex-col divide-y divide-gray-200 dark:divide-white/10">
    @forelse ($listings as $listing)
        @php($isSelected = $selected !== null && $selected->id === $listing->id)
        <a
            href="{{ route('seller.listings.show', $listing) }}"
            data-pane-cell="{{ $listing->id }}"
            @if ($isSelected) aria-current="page" @endif
            class="flex items-center gap-3 px-6 py-3 hover:bg-gray-50 dark:hover:bg-white/5 {{ $isSelected ? 'bg-gray-50 shadow-[inset_2px_0_0_0_#4f46e5] dark:bg-white/5 dark:shadow-[inset_2px_0_0_0_#6366f1]' : '' }}"
        >
            <img src="{{ $listing->imageUrl() }}" alt="" class="size-10 shrink-0 rounded-md object-cover">

            <div class="min-w-0 flex-1">
                <p class="truncate text-sm font-semibold text-gray-900 dark:text-gray-100">{{ $listing->title }}</p>
                <p class="truncate text-xs text-gray-500 dark:text-gray-400">{{ $listing->price()->format() }} &middot; {{ \App\Domain\Listings\ListingStockLabel::withInStock($listing->quantity) }}</p>
            </div>

            <x-seller.listing-status-badge :listing="$listing" class="shrink-0" />
        </a>
    @empty
        <p class="m-3 rounded border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-900 p-4 text-gray-600 dark:text-gray-400">No listings yet. Start with a new one.</p>
    @endforelse
</div>
