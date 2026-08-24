<x-layouts.admin title="Listings — Art Store admin">
    <h1 class="text-xl font-semibold">Listings</h1>

    <x-admin.filters :action="route('admin.listings.index')">
        <x-admin.status-filter :cases="$statuses" :selected="$status" />
        <x-admin.seller-filter :sellers="$sellers" :selected="$sellerId" />
        <x-admin.removed-filter :cases="$removedFilters" :selected="$removed" />
    </x-admin.filters>

    <x-admin.listings-table :listings="$listings" caption="Every listing across every seller" />
</x-layouts.admin>
