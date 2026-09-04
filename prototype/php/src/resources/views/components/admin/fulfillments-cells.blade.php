{{-- The fulfillments list pane's cells, in the shared pane-row shape: the
     seller leads — whose work it is — because that is the shop a founder
     is tracking; the muted line is the scan line — who it ships to and the
     seller's net; the status pill (`FulfillmentStatus::sellerBadgeTint()`,
     the one tint both portals read) and the shipped (or created) date sit
     in the meta column. --}}
@props(['fulfillments', 'selected' => null])

<div class="flex flex-col divide-y divide-stone-200 dark:divide-white/10">
    @forelse ($fulfillments as $fulfillment)
        @php
            $isSelected = $selected !== null && $selected->id === $fulfillment->id;
            $tint = $fulfillment->status->sellerBadgeTint();
            $when = $fulfillment->shipped_at ?? $fulfillment->created_at;
        @endphp
        <x-pane-row
            accent="stone"
            :selected="$isSelected"
            href="{{ route('admin.fulfillments.show', $fulfillment) }}"
            :aria-current="$isSelected ? 'page' : null"
            data-pane-cell="{{ $fulfillment->id }}"
        >
            <x-slot:title>
                <p class="truncate text-sm/6 font-semibold text-stone-900 dark:text-white">{{ $fulfillment->seller->displayName() }}</p>
            </x-slot:title>
            <x-slot:supporting>
                <p class="mt-1 truncate text-xs/5 text-stone-500 dark:text-stone-400">
                    {{ $fulfillment->order->customer->displayName() }} &middot; {{ $fulfillment->net()->format() }}
                </p>
            </x-slot:supporting>
            <x-slot:meta>
                <x-admin.status-pill :tint="$tint">{{ $fulfillment->status->label() }}</x-admin.status-pill>
                <p class="mt-1 text-xs/5 text-stone-500 dark:text-stone-400">{{ $when?->format('M j') }}</p>
            </x-slot:meta>
        </x-pane-row>
    @empty
        <x-admin.nothing class="m-3">No fulfillments.</x-admin.nothing>
    @endforelse
</div>
