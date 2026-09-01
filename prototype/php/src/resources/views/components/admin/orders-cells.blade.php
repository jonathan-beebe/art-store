{{-- The orders list pane's cells (DSGN-006, restyled to the canonical
     two-line row): the customer leads because that is what a founder
     recognises, not the order id (`docs/admin.md`); the status pill sits
     right-aligned beside it, and the muted second line is the scan line —
     item count, total, and the date placed. --}}
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
        <a
            href="{{ route('admin.orders.show', $order) }}"
            @if ($isSelected) aria-current="true" @endif
            data-pane-cell="{{ $order->id }}"
            class="flex items-center gap-3 px-6 py-3 hover:bg-stone-50 dark:hover:bg-white/5 {{ $isSelected ? 'bg-stone-50 shadow-[inset_2px_0_0_0_var(--color-stone-500)] dark:bg-white/5 dark:shadow-[inset_2px_0_0_0_var(--color-stone-400)]' : '' }}"
        >
            <div class="min-w-0 flex-1">
                <div class="flex items-start gap-3">
                    <span class="flex-1 truncate text-sm font-semibold text-stone-900 dark:text-stone-100">{{ $order->customer->displayName() }}</span>
                    <x-admin.status-pill :tint="$tint">{{ $order->status->label() }}</x-admin.status-pill>
                </div>
                <p class="truncate text-xs text-stone-500 dark:text-stone-400">
                    {{ $order->items_count }} item{{ $order->items_count === 1 ? '' : 's' }} &middot; {{ $order->total()->format() }} &middot; {{ $order->placed_at?->format('M j') }}
                </p>
            </div>
        </a>
    @empty
        <x-admin.nothing class="m-3">No orders.</x-admin.nothing>
    @endforelse
</div>
