{{-- The listings list pane's cells (DSGN-006, restyled to the canonical
     two-line row). The seller is the supporting fact on the muted second
     line, beside the price (or "no price" for a draft that has not set
     one). An active removal overrides the status pill — a removed for-sale
     listing reads as removed, not as still for sale. --}}
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
        <a
            href="{{ route('admin.listings.show', $listing) }}"
            @if ($isSelected) aria-current="true" @endif
            data-pane-cell="{{ $listing->id }}"
            class="flex items-center gap-3 px-6 py-3 hover:bg-stone-50 dark:hover:bg-white/5 {{ $isSelected ? 'bg-stone-50 shadow-[inset_2px_0_0_0_var(--color-stone-500)] dark:bg-white/5 dark:shadow-[inset_2px_0_0_0_var(--color-stone-400)]' : '' }}"
        >
            <div class="min-w-0 flex-1">
                <div class="flex items-start gap-3">
                    <span class="flex-1 truncate text-sm font-semibold text-stone-900 dark:text-stone-100">{{ $listing->title }}</span>
                    <x-admin.status-pill :tint="$tint">{{ $statusLabel }}</x-admin.status-pill>
                </div>
                <p class="truncate text-xs text-stone-500 dark:text-stone-400">
                    {{ $listing->seller->displayName() }} &middot; {{ $listing->price()->format() }}
                </p>
            </div>
        </a>
    @empty
        <x-admin.nothing class="m-3">No listings.</x-admin.nothing>
    @endforelse
</div>
