{{--
    The seller orders list pane's rows: the buyer's name leads (who a seller
    recognises a sale by), the status badge sits right-aligned beside it,
    and the muted second line is the scan line — item, quantity, the total
    the buyer paid, and the date placed.
--}}
@props(['fulfillments', 'selected' => null])

<div class="flex flex-col divide-y divide-gray-200 dark:divide-white/10">
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
            $items = $fulfillment->order->items;
            $primary = $items->first();
            $itemLabel = $primary?->title ?? '—';
            if ($primary && $primary->quantity > 1) {
                $itemLabel .= ' ×'.$primary->quantity;
            }
            if ($items->count() > 1) {
                $itemLabel .= ' +'.($items->count() - 1).' more';
            }
        @endphp
        <a
            href="{{ route('seller.orders.show', $fulfillment) }}"
            @if ($isSelected) aria-current="true" @endif
            data-pane-cell="{{ $fulfillment->id }}"
            class="flex flex-col gap-1 p-4 hover:bg-gray-50 dark:hover:bg-white/5 {{ $isSelected ? 'bg-gray-50 shadow-[inset_2px_0_0_0_var(--color-indigo-600)] dark:bg-white/5 dark:shadow-[inset_2px_0_0_0_var(--color-indigo-500)]' : '' }}"
        >
            <div class="flex items-start gap-3">
                <span class="flex-1 truncate text-sm font-semibold text-gray-900 dark:text-gray-100">{{ $fulfillment->order->shipping_name }}</span>
                <x-seller.status-badge :tint="$tint">{{ $fulfillment->status->label() }}</x-seller.status-badge>
            </div>
            <p class="truncate text-xs text-gray-500 dark:text-gray-400">
                {{ $itemLabel }} · {{ $fulfillment->subtotal()->format() }} · {{ $fulfillment->order->placed_at?->format('M j') }}
            </p>
        </a>
    @empty
        <p class="m-3 rounded-md border border-gray-200 dark:border-white/10 bg-white dark:bg-gray-900 p-4 text-sm text-gray-600 dark:text-gray-400">No orders yet.</p>
    @endforelse
</div>
