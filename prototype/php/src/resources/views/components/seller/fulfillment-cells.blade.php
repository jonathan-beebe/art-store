{{--
    The seller orders list pane's rows: the buyer's name leads (who a
    seller recognises a sale by), the muted line is the scan line — item,
    quantity, and the total the buyer paid — and the status badge and the
    date placed sit in the meta column.
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
        <x-pane-row
            accent="indigo"
            :selected="$isSelected"
            href="{{ route('seller.orders.show', $fulfillment) }}"
            :aria-current="$isSelected ? 'true' : null"
            data-pane-cell="{{ $fulfillment->id }}"
        >
            <x-slot:title>
                <p class="truncate text-sm/6 font-semibold text-gray-900 dark:text-white">{{ $fulfillment->order->shipping_name }}</p>
            </x-slot:title>
            <x-slot:supporting>
                <p class="mt-1 truncate text-xs/5 text-gray-500 dark:text-gray-400">
                    {{ $itemLabel }} · {{ $fulfillment->subtotal()->format() }}
                </p>
            </x-slot:supporting>
            <x-slot:meta>
                <x-seller.status-badge :tint="$tint">{{ $fulfillment->status->label() }}</x-seller.status-badge>
                <p class="mt-1 text-xs/5 text-gray-500 dark:text-gray-400">{{ $fulfillment->order->placed_at?->format('M j') }}</p>
            </x-slot:meta>
        </x-pane-row>
    @empty
        <p class="m-3 rounded-md border border-gray-200 dark:border-white/10 bg-white dark:bg-gray-900 p-4 text-sm text-gray-600 dark:text-gray-400">No orders yet.</p>
    @endforelse
</div>
