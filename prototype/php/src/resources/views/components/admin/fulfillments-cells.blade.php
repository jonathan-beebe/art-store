{{-- The fulfillments list pane's cells (DSGN-006). The seller leads —
     whose work it is — because that is the shop a founder is tracking;
     the customer it ships to is the supporting fact. The net a seller
     nets is the number that matters, right-aligned in mono. --}}
@props(['fulfillments', 'selected' => null])

<div class="flex flex-col divide-y divide-gray-200 dark:divide-gray-800">
    @forelse ($fulfillments as $fulfillment)
        @php
            $isSelected = $selected !== null && $selected->id === $fulfillment->id;
            $tint = match ($fulfillment->status) {
                \App\Domain\Orders\FulfillmentStatus::Declined, \App\Domain\Orders\FulfillmentStatus::Refunded => 'bad',
                \App\Domain\Orders\FulfillmentStatus::AwaitingShipment => 'warn',
                \App\Domain\Orders\FulfillmentStatus::Shipped, \App\Domain\Orders\FulfillmentStatus::Delivered => 'ok',
            };
        @endphp
        <x-admin.card-row
            href="{{ route('admin.fulfillments.show', $fulfillment) }}"
            :aria-current="$isSelected ? 'true' : null"
            class="{{ $isSelected ? 'bg-gray-100 dark:bg-gray-800' : '' }}"
        >
            <div class="flex items-baseline gap-2">
                <span class="truncate font-medium">{{ $fulfillment->seller->displayName() }}</span>
                <span class="flex-1"></span>
                <x-admin.cell-time :at="$fulfillment->shipped_at ?? $fulfillment->created_at" />
            </div>
            <div class="flex items-center gap-2 text-gray-600 dark:text-gray-400">
                <x-admin.status-badge :tint="$tint">{{ $fulfillment->status->label() }}</x-admin.status-badge>
                <span class="truncate">{{ $fulfillment->order->customer->displayName() }}</span>
                <span class="flex-1"></span>
                <span class="font-mono tabular-nums text-gray-900 dark:text-gray-100">{{ $fulfillment->net()->format() }}</span>
            </div>
        </x-admin.card-row>
    @empty
        <x-admin.nothing class="m-3">No fulfillments.</x-admin.nothing>
    @endforelse
</div>
