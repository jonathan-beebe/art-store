<x-layouts.admin title="Orders — Art Store admin" mode="list" empty-detail-prompt="Choose an order to see its details.">
    <x-slot:cells>
        <div class="flex items-baseline gap-2 border-b border-stone-200 px-6 py-4 dark:border-white/10">
            <h1 class="text-sm font-semibold text-stone-900 dark:text-stone-100">Orders</h1>
            <span class="text-xs text-stone-500 dark:text-stone-400">{{ $ordersTotal }}</span>
        </div>
        <div class="flex-1 overflow-y-auto">
            <x-admin.orders-cells :orders="$orders" />
        </div>
        <x-admin.cell-footer :shown="$orders->count()" :total="$ordersTotal" :route="route('admin.orders.index')" />
    </x-slot:cells>

    <h1 class="text-xl font-semibold text-stone-900 dark:text-stone-100">Orders</h1>

    <x-admin.filters :action="route('admin.orders.index')">
        <x-admin.status-filter :cases="$statuses" :selected="$status" />
        <x-admin.customer-filter :customers="$customers" :selected="$customerId" />
    </x-admin.filters>

    <x-admin.orders-table :orders="$orders" caption="Every order on the platform" />
</x-layouts.admin>
