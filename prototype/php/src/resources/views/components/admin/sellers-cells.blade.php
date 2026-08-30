{{-- The sellers list pane's cells (DSGN-006). Sellers carry no status
     enum, so line 2 has no badge — the email is the supporting fact and
     the available escrow balance is the number that matters. --}}
@props(['sellers', 'balances', 'selected' => null])

<div class="flex flex-col divide-y divide-gray-200 dark:divide-gray-800">
    @forelse ($sellers as $seller)
        @php
            $isSelected = $selected !== null && $selected->id === $seller->id;
            $balance = $balances->of($seller->id);
        @endphp
        <x-admin.card-row
            href="{{ route('admin.sellers.show', $seller) }}"
            :aria-current="$isSelected ? 'true' : null"
            data-pane-cell="{{ $seller->id }}"
            class="{{ $isSelected ? 'bg-gray-100 dark:bg-gray-800' : '' }}"
        >
            <div class="flex items-baseline gap-2">
                <span class="truncate font-medium">{{ $seller->displayName() }}</span>
                <span class="flex-1"></span>
                <x-admin.cell-time :at="$seller->created_at" />
            </div>
            <div class="flex items-center gap-2 text-gray-600 dark:text-gray-400">
                <span class="truncate">{{ $seller->email }}</span>
                <span class="flex-1"></span>
                <span class="font-mono tabular-nums text-gray-900 dark:text-gray-100">{{ $balance->available->format() }}</span>
            </div>
        </x-admin.card-row>
    @empty
        <x-admin.nothing class="m-3">No sellers yet.</x-admin.nothing>
    @endforelse
</div>
