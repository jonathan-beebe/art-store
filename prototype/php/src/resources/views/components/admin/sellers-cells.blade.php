{{-- The sellers list pane's cells, in the shared pane-row shape: an
     initials mark leads, the shop name titles the row, and the available
     escrow balance is the meta column's only fact — the created date is a
     low-value fact in a sellers list and gave the truncating "Weasleys'
     Wizard Wheezes" no room. Sellers carry no status enum, so the meta
     column has no pill. A seller with no shop name falls back to their
     email as the title (`displayName()`), so the supporting line — the
     email too — is dropped rather than repeat it. --}}
@props(['sellers', 'balances', 'selected' => null])

<div class="flex flex-col divide-y divide-stone-200 dark:divide-stone-800">
    @forelse ($sellers as $seller)
        @php
            $isSelected = $selected !== null && $selected->id === $seller->id;
            $balance = $balances->of($seller->id);
            $name = $seller->displayName();
            $initials = collect(preg_split('/\s+/', trim($name)))
                ->filter()
                ->take(2)
                ->map(fn (string $word): string => mb_strtoupper(mb_substr($word, 0, 1)))
                ->implode('');
        @endphp
        <x-pane-row
            accent="stone"
            :selected="$isSelected"
            href="{{ route('admin.sellers.show', $seller) }}"
            :aria-current="$isSelected ? 'page' : null"
            data-pane-cell="{{ $seller->id }}"
        >
            <x-slot:leading>
                <span class="flex size-12 flex-none items-center justify-center rounded-full bg-stone-100 text-sm font-medium text-stone-600 dark:bg-stone-800 dark:text-stone-300">{{ $initials }}</span>
            </x-slot:leading>
            <x-slot:title>
                <p class="truncate text-sm/6 font-semibold text-stone-900 dark:text-white">{{ $name }}</p>
            </x-slot:title>
            @if ($seller->email !== $name)
                <x-slot:supporting>
                    <p class="mt-1 truncate text-xs/5 text-stone-500 dark:text-stone-400">{{ $seller->email }}</p>
                </x-slot:supporting>
            @endif
            <x-slot:meta>
                <p class="text-sm/6 font-mono tabular-nums text-stone-900 dark:text-white">{{ $balance->available->format() }}</p>
            </x-slot:meta>
        </x-pane-row>
    @empty
        <x-admin.nothing class="m-3">No sellers yet.</x-admin.nothing>
    @endforelse
</div>
