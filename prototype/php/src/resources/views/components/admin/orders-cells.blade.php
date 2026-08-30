{{-- The orders list pane's cells (DSGN-006): line 1 is who and when, line
     2 is the state a founder scans for — status, item count, and the
     total, right-aligned in mono. The customer leads because that is what
     a founder recognises, not the order id (`docs/admin.md`). --}}
@props(['orders', 'selected' => null])

<div class="flex flex-col divide-y divide-gray-200 dark:divide-gray-800">
    @forelse ($orders as $order)
        @php
            $isSelected = $selected !== null && $selected->id === $order->id;
            $tint = match ($order->status) {
                \App\Domain\Orders\OrderStatus::Cancelled, \App\Domain\Orders\OrderStatus::PaymentFailed, \App\Domain\Orders\OrderStatus::Refunded => 'bad',
                \App\Domain\Orders\OrderStatus::PendingVerification, \App\Domain\Orders\OrderStatus::AwaitingPayment => 'warn',
                \App\Domain\Orders\OrderStatus::Paid, \App\Domain\Orders\OrderStatus::PartiallyShipped, \App\Domain\Orders\OrderStatus::Shipped, \App\Domain\Orders\OrderStatus::Delivered => 'ok',
            };
        @endphp
        <x-admin.card-row
            href="{{ route('admin.orders.show', $order) }}"
            :aria-current="$isSelected ? 'true' : null"
            class="{{ $isSelected ? 'bg-gray-100 dark:bg-gray-800' : '' }}"
        >
            <div class="flex items-baseline gap-2">
                <span class="truncate font-medium">{{ $order->customer->displayName() }}</span>
                <span class="flex-1"></span>
                <x-admin.cell-time :at="$order->placed_at" />
            </div>
            <div class="flex items-center gap-2 text-gray-600 dark:text-gray-400">
                <x-admin.status-badge :tint="$tint">{{ $order->status->label() }}</x-admin.status-badge>
                <span class="truncate">{{ $order->items_count }} item{{ $order->items_count === 1 ? '' : 's' }}</span>
                <span class="flex-1"></span>
                <span class="font-mono tabular-nums text-gray-900 dark:text-gray-100">{{ $order->total()->format() }}</span>
            </div>
        </x-admin.card-row>
    @empty
        <x-admin.nothing class="m-3">No orders.</x-admin.nothing>
    @endforelse
</div>
