{{-- The listings list pane's cells, in the shared pane-row shape: the
     listing's own image leads, the seller is the supporting fact on the
     muted line, beside the price (or "no price" for a draft that has not
     set one), and the status pill sits in the meta column. An active
     removal overrides the pill — a removed for-sale listing reads as
     removed, not as still for sale. --}}
@props(['listings', 'selected' => null])

<div class="flex flex-col divide-y divide-stone-200 dark:divide-white/10">
    @forelse ($listings as $listing)
        @php
            $isSelected = $selected !== null && $selected->id === $listing->id;
            $tint = match (true) {
                (bool) $listing->activeRemoval => 'red',
                $listing->status === \App\Domain\Listings\ListingStatus::ForSale => 'green',
                $listing->status === \App\Domain\Listings\ListingStatus::Draft => 'yellow',
                default => 'gray',
            };
            $statusLabel = $listing->activeRemoval ? 'Removed' : $listing->status->label();
        @endphp
        <x-pane-row
            accent="stone"
            :selected="$isSelected"
            href="{{ route('admin.listings.show', $listing) }}"
            :aria-current="$isSelected ? 'page' : null"
            data-pane-cell="{{ $listing->id }}"
        >
            <x-slot:leading>
                <img src="{{ $listing->imageUrl() }}" alt="" class="size-12 flex-none rounded-md object-cover">
            </x-slot:leading>
            <x-slot:title>
                <p class="truncate text-sm/6 font-semibold text-stone-900 dark:text-white">{{ $listing->title }}</p>
            </x-slot:title>
            <x-slot:supporting>
                <p class="mt-1 truncate text-xs/5 text-stone-500 dark:text-stone-400">
                    {{ $listing->seller->displayName() }} &middot; {{ $listing->price()->format() }}
                </p>
            </x-slot:supporting>
            <x-slot:meta>
                <x-admin.status-pill :tint="$tint">{{ $statusLabel }}</x-admin.status-pill>
            </x-slot:meta>
        </x-pane-row>
    @empty
        <x-admin.nothing class="m-3">No listings.</x-admin.nothing>
    @endforelse
</div>
