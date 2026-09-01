{{-- The fulfillments list pane's cells (DSGN-006, restyled to the canonical
     two-line row): the seller leads — whose work it is — because that is
     the shop a founder is tracking; the status pill sits right-aligned
     beside it, using the same status-to-color mapping the seller portal's
     own fulfillment-cells uses. The muted second line is the scan line —
     who it ships to, the seller's net, and the shipped (or created) date. --}}
@props(['fulfillments', 'selected' => null])

<div class="flex flex-col divide-y divide-stone-200 dark:divide-white/10">
    @forelse ($fulfillments as $fulfillment)
        @php
            $isSelected = $selected !== null && $selected->id === $fulfillment->id;
            $tint = match ($fulfillment->status) {
                \App\Domain\Orders\FulfillmentStatus::AwaitingShipment => 'yellow',
                \App\Domain\Orders\FulfillmentStatus::Shipped => 'blue',
                \App\Domain\Orders\FulfillmentStatus::Delivered => 'green',
                \App\Domain\Orders\FulfillmentStatus::Refunded => 'red',
                \App\Domain\Orders\FulfillmentStatus::Declined => 'gray',
            };
            $when = $fulfillment->shipped_at ?? $fulfillment->created_at;
        @endphp
        <a
            href="{{ route('admin.fulfillments.show', $fulfillment) }}"
            @if ($isSelected) aria-current="true" @endif
            data-pane-cell="{{ $fulfillment->id }}"
            class="flex items-center gap-3 px-6 py-3 hover:bg-stone-50 dark:hover:bg-white/5 {{ $isSelected ? 'bg-stone-50 shadow-[inset_2px_0_0_0_var(--color-stone-500)] dark:bg-white/5 dark:shadow-[inset_2px_0_0_0_var(--color-stone-400)]' : '' }}"
        >
            <div class="min-w-0 flex-1">
                <div class="flex items-start gap-3">
                    <span class="flex-1 truncate text-sm font-semibold text-stone-900 dark:text-stone-100">{{ $fulfillment->seller->displayName() }}</span>
                    <x-admin.status-pill :tint="$tint">{{ $fulfillment->status->label() }}</x-admin.status-pill>
                </div>
                <p class="truncate text-xs text-stone-500 dark:text-stone-400">
                    {{ $fulfillment->order->customer->displayName() }} &middot; {{ $fulfillment->net()->format() }} &middot; {{ $when?->format('M j') }}
                </p>
            </div>
        </a>
    @empty
        <x-admin.nothing class="m-3">No fulfillments.</x-admin.nothing>
    @endforelse
</div>
