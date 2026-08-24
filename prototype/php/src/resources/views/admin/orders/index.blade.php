<x-layouts.admin title="Orders — Art Store admin">
    <h1 class="text-xl font-semibold">Orders</h1>

    <x-admin.filters :action="route('admin.orders.index')">
        <x-admin.status-filter :cases="$statuses" :selected="$status" />
        <x-admin.customer-filter :customers="$customers" :selected="$customerId" />
    </x-admin.filters>

    <x-admin.orders-table :orders="$orders" caption="Every order on the platform" />
</x-layouts.admin>
