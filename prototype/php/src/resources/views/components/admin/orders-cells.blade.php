{{-- The orders list pane's cells, in the shared pane-row shape: the
     customer leads because that is what a founder recognises, not the
     order id (`docs/admin.md`); the muted line is the scan line — item
     count and total; the status pill and the date placed sit in the meta
     column. --}}
@props(['orders', 'selected' => null])

<div class="flex flex-col divide-y divide-stone-200 dark:divide-white/10">
    @forelse ($orders as $order)
        @php
            $isSelected = $selected !== null && $selected->id === $order->id;
            $tint = match ($order->status) {
                \App\Domain\Orders\OrderStatus::Cancelled, \App\Domain\Orders\OrderStatus::PaymentFailed, \App\Domain\Orders\OrderStatus::Refunded => 'red',
                \App\Domain\Orders\OrderStatus::PendingVerification, \App\Domain\Orders\OrderStatus::AwaitingPayment => 'yellow',
                \App\Domain\Orders\OrderStatus::Paid, \App\Domain\Orders\OrderStatus::PartiallyShipped, \App\Domain\Orders\OrderStatus::Shipped, \App\Domain\Orders\OrderStatus::Delivered => 'green',
            };
        @endphp
        <x-pane-row
            accent="stone"
            :selected="$isSelected"
            href="{{ route('admin.orders.show', $order) }}"
            :aria-current="$isSelected ? 'true' : null"
            data-pane-cell="{{ $order->id }}"
        >
            <x-slot:title>
                <p class="truncate text-sm/6 font-semibold text-stone-900 dark:text-white">{{ $order->customer->displayName() }}</p>
            </x-slot:title>
            <x-slot:supporting>
                <p class="mt-1 truncate text-xs/5 text-stone-500 dark:text-stone-400">
                    {{ $order->items_count }} item{{ $order->items_count === 1 ? '' : 's' }} &middot; {{ $order->total()->format() }}
                </p>
            </x-slot:supporting>
            <x-slot:meta>
                <x-admin.status-pill :tint="$tint">{{ $order->status->label() }}</x-admin.status-pill>
                <p class="mt-1 text-xs/5 text-stone-500 dark:text-stone-400">{{ $order->placed_at?->format('M j') }}</p>
            </x-slot:meta>
        </x-pane-row>
    @empty
        <x-admin.nothing class="m-3">No orders.</x-admin.nothing>
    @endforelse
</div>
