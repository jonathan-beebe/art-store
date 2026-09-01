{{-- The sellers list pane's cells (DSGN-006). Sellers carry no status
     enum, so line 2 has no badge — the email is the supporting fact and
     the available escrow balance is the number that matters. --}}
@props(['sellers', 'balances', 'selected' => null])

<div class="flex flex-col divide-y divide-stone-200 dark:divide-stone-800">
    @forelse ($sellers as $seller)
        @php
            $isSelected = $selected !== null && $selected->id === $seller->id;
            $balance = $balances->of($seller->id);
        @endphp
        <x-admin.card-row
            href="{{ route('admin.sellers.show', $seller) }}"
            :aria-current="$isSelected ? 'true' : null"
            data-pane-cell="{{ $seller->id }}"
            class="{{ $isSelected ? 'bg-stone-50 shadow-[inset_2px_0_0_0_var(--color-stone-500)] dark:bg-stone-800/60 dark:shadow-[inset_2px_0_0_0_var(--color-stone-500)]' : '' }}"
        >
            <div class="flex items-baseline gap-2">
                <span class="truncate font-medium">{{ $seller->displayName() }}</span>
                <span class="flex-1"></span>
                <x-admin.cell-time :at="$seller->created_at" />
            </div>
            <div class="flex items-center gap-2 text-stone-600 dark:text-stone-400">
                <span class="truncate">{{ $seller->email }}</span>
                <span class="flex-1"></span>
                <span class="font-mono tabular-nums text-stone-900 dark:text-stone-100">{{ $balance->available->format() }}</span>
            </div>
        </x-admin.card-row>
    @empty
        <x-admin.nothing class="m-3">No sellers yet.</x-admin.nothing>
    @endforelse
</div>
