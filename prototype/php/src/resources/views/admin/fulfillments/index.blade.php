<x-layouts.admin title="Fulfillments — Art Store admin">
    <h1 class="text-xl font-semibold">Fulfillments</h1>

    <x-admin.filters :action="route('admin.fulfillments.index')">
        <x-admin.status-filter :cases="$statuses" :selected="$status" />
        <x-admin.seller-filter :sellers="$sellers" :selected="$sellerId" />
    </x-admin.filters>

    <x-admin.fulfillments-table :fulfillments="$fulfillments" caption="Every fulfillment on the platform" />
</x-layouts.admin>
