{{-- The listings list pane's cells (DSGN-006). The seller is the
     supporting fact on line 2; price is the number that matters, or "no
     price" in mono for a draft that has not set one. An active removal
     overrides the status badge — a removed for-sale listing reads as
     removed, not as still for sale. --}}
@props(['listings', 'selected' => null])

<div class="flex flex-col divide-y divide-gray-200 dark:divide-gray-800">
    @forelse ($listings as $listing)
        @php
            $isSelected = $selected !== null && $selected->id === $listing->id;
            $tint = match (true) {
                (bool) $listing->activeRemoval => 'bad',
                $listing->status === \App\Domain\Listings\ListingStatus::ForSale => 'ok',
                $listing->status === \App\Domain\Listings\ListingStatus::Draft => 'warn',
                default => 'neutral',
            };
            $statusLabel = $listing->activeRemoval ? 'Removed' : $listing->status->label();
        @endphp
        <x-admin.card-row
            href="{{ route('admin.listings.show', $listing) }}"
            :aria-current="$isSelected ? 'true' : null"
            class="{{ $isSelected ? 'bg-gray-100 dark:bg-gray-800' : '' }}"
        >
            <div class="flex items-baseline gap-2">
                <span class="truncate font-medium">{{ $listing->title }}</span>
                <span class="flex-1"></span>
                <x-admin.cell-time :at="$listing->created_at" />
            </div>
            <div class="flex items-center gap-2 text-gray-600 dark:text-gray-400">
                <x-admin.status-badge :tint="$tint">{{ $statusLabel }}</x-admin.status-badge>
                <span class="truncate">{{ $listing->seller->displayName() }}</span>
                <span class="flex-1"></span>
                <span class="font-mono tabular-nums text-gray-900 dark:text-gray-100">{{ $listing->price()->format() }}</span>
            </div>
        </x-admin.card-row>
    @empty
        <x-admin.nothing class="m-3">No listings.</x-admin.nothing>
    @endforelse
</div>
