<x-layouts.admin title="Listings — Art Store admin" mode="list" empty-detail-prompt="Choose a listing to see its details.">
    <x-slot:cells>
        <div class="flex items-baseline gap-2 border-b border-gray-200 p-3 dark:border-gray-800">
            <h1 class="text-sm font-semibold">Listings</h1>
            <span class="text-xs text-gray-500 dark:text-gray-400">{{ $listings->count() }}</span>
        </div>
        <div class="flex-1 overflow-y-auto">
            <x-admin.listings-cells :listings="$listings" />
        </div>
    </x-slot:cells>

    <h1 class="text-xl font-semibold">Listings</h1>

    <x-admin.filters :action="route('admin.listings.index')">
        <x-admin.status-filter :cases="$statuses" :selected="$status" />
        <x-admin.seller-filter :sellers="$sellers" :selected="$sellerId" />
        <x-admin.removed-filter :cases="$removedFilters" :selected="$removed" />
    </x-admin.filters>

    <x-admin.listings-table :listings="$listings" caption="Every listing across every seller" />
</x-layouts.admin>
