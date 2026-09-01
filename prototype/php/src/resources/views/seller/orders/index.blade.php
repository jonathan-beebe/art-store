<x-layouts.seller title="Orders — Art Store seller">
    <div class="h-[calc(100dvh-7rem)] overflow-hidden rounded-lg border border-gray-200 dark:border-white/10">
        <x-seller.list-detail mobile="list">
            <x-slot:listHeader>
                <div class="flex items-baseline gap-2">
                    <h1 class="text-sm font-semibold text-gray-900 dark:text-gray-100">Orders</h1>
                    @if ($needsActionCount > 0)
                        <span class="text-xs text-gray-500 dark:text-gray-400">{{ $needsActionCount }} need action</span>
                    @endif
                </div>
            </x-slot:listHeader>

            <x-slot:list>
                <x-seller.fulfillment-cells :fulfillments="$fulfillments" />
            </x-slot:list>

            <div class="hidden h-full items-center justify-center p-8 lg:flex">
                <p class="text-sm text-gray-500 dark:text-gray-500">Choose an order to see its details.</p>
            </div>
        </x-seller.list-detail>
    </div>
</x-layouts.seller>
