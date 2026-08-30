<x-layouts.admin title="Fulfillments — Art Store admin" mode="list" empty-detail-prompt="Choose a fulfillment to see its details.">
    <x-slot:cells>
        <div class="flex items-baseline gap-2 border-b border-gray-200 p-3 dark:border-gray-800">
            <h1 class="text-sm font-semibold">Fulfillments</h1>
            <span class="text-xs text-gray-500 dark:text-gray-400">{{ $fulfillmentsTotal }}</span>
        </div>
        <div class="flex-1 overflow-y-auto">
            <x-admin.fulfillments-cells :fulfillments="$fulfillments" />
        </div>
        <x-admin.cell-footer :shown="$fulfillments->count()" :total="$fulfillmentsTotal" :route="route('admin.fulfillments.index')" />
    </x-slot:cells>

    <h1 class="text-xl font-semibold">Fulfillments</h1>

    <x-admin.filters :action="route('admin.fulfillments.index')">
        <x-admin.status-filter :cases="$statuses" :selected="$status" />
        <x-admin.seller-filter :sellers="$sellers" :selected="$sellerId" />
    </x-admin.filters>

    <x-admin.fulfillments-table :fulfillments="$fulfillments" caption="Every fulfillment on the platform" />
</x-layouts.admin>
