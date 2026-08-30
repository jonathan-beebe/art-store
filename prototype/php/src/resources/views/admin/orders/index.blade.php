<x-layouts.admin title="Orders — Art Store admin" mode="list" empty-detail-prompt="Choose an order to see its details.">
    <x-slot:cells>
        <div class="flex items-baseline gap-2 border-b border-gray-200 p-3 dark:border-gray-800">
            <h1 class="text-sm font-semibold">Orders</h1>
            <span class="text-xs text-gray-500 dark:text-gray-400">{{ $ordersTotal }}</span>
        </div>
        <div class="flex-1 overflow-y-auto">
            <x-admin.orders-cells :orders="$orders" />
        </div>
        <x-admin.cell-footer :shown="$orders->count()" :total="$ordersTotal" :route="route('admin.orders.index')" />
    </x-slot:cells>

    <h1 class="text-xl font-semibold">Orders</h1>

    <x-admin.filters :action="route('admin.orders.index')">
        <x-admin.status-filter :cases="$statuses" :selected="$status" />
        <x-admin.customer-filter :customers="$customers" :selected="$customerId" />
    </x-admin.filters>

    <x-admin.orders-table :orders="$orders" caption="Every order on the platform" />
</x-layouts.admin>
