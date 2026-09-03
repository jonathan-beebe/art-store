{{--
    The Listings list pane's rows (Listings.dc.html), shared by the index
    and show views so both render the exact same list — the shared pane-row
    shape the admin listings pane also renders through
    (App\Http\Controllers\Admin\ListingController, x-admin.listings-cells).
    The listing's own cover image, or its generated placeholder (every
    listing has one or the other, never neither) leads; a truncating title
    and a muted price-and-stock line follow; the status badge sits in the
    meta column.
--}}
@props(['listings', 'selected' => null])

<div class="flex flex-col divide-y divide-gray-200 dark:divide-white/10">
    @forelse ($listings as $listing)
        @php($isSelected = $selected !== null && $selected->id === $listing->id)
        <x-pane-row
            accent="indigo"
            :selected="$isSelected"
            href="{{ route('seller.listings.show', $listing) }}"
            data-pane-cell="{{ $listing->id }}"
            :aria-current="$isSelected ? 'page' : null"
        >
            <x-slot:leading>
                <img src="{{ $listing->imageUrl() }}" alt="" class="size-12 flex-none rounded-md object-cover">
            </x-slot:leading>
            <x-slot:title>
                <p class="truncate text-sm/6 font-semibold text-gray-900 dark:text-white">{{ $listing->title }}</p>
            </x-slot:title>
            <x-slot:supporting>
                <p class="mt-1 truncate text-xs/5 text-gray-500 dark:text-gray-400">{{ $listing->price()->format() }} &middot; {{ \App\Domain\Listings\ListingStockLabel::withInStock($listing->quantity) }}</p>
            </x-slot:supporting>
            <x-slot:meta>
                <x-seller.listing-status-badge :listing="$listing" />
            </x-slot:meta>
        </x-pane-row>
    @empty
        <p class="m-3 rounded border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-900 p-4 text-gray-600 dark:text-gray-400">No listings yet. Start with a new one.</p>
    @endforelse
</div>
