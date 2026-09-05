<x-layouts.seller title="Orders — Art Store seller" :bleed="true">
    <div class="h-[calc(100dvh-4rem)] overflow-hidden">
        <x-seller.list-detail mobile="list">
            <x-slot:listHeader>
                <h1 class="text-sm font-semibold text-gray-900 dark:text-gray-100">Orders</h1>
                <x-seller.lane-tabs :tabs="$tabs" />
            </x-slot:listHeader>

            <x-slot:list>
                <x-seller.fulfillment-cells :pane="$pane" />
                <x-seller.cell-footer :shown="$pane->shown()" :total="$pane->total" />
            </x-slot:list>

            <div class="hidden h-full items-center justify-center p-8 lg:flex">
                <p class="text-sm text-gray-500 dark:text-gray-500">Choose an order to see its details.</p>
            </div>
        </x-seller.list-detail>
    </div>
</x-layouts.seller>
