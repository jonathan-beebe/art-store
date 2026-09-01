<x-layouts.seller title="Listings — Art Store seller" :bleed="true">
    <div class="h-[calc(100dvh-4rem)] overflow-hidden">
        <x-seller.list-detail>
            <x-slot:listHeader>
                @include('seller.listings._list-header', ['total' => $listingsTotal])
            </x-slot:listHeader>

            <x-slot:list>
                <x-seller.listing-rows :listings="$listings" />
                <x-seller.cell-footer :shown="$listings->count()" :total="$listingsTotal" />
            </x-slot:list>

            <div class="flex h-full items-center justify-center p-8 text-center">
                <p class="text-gray-500 dark:text-gray-500">Choose a listing to see its details.</p>
            </div>
        </x-seller.list-detail>
    </div>
</x-layouts.seller>
