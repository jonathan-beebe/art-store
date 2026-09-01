<x-layouts.seller title="Listings — Art Store seller">
    {{--
        Cancels the layout's own gutter and vertical padding at `lg`+ so the
        split pane can run the full height beneath the top bar, the way
        Listings.dc.html draws it — below `lg` the list is a normal page,
        so it keeps the layout's padding and scrolls with the document.
    --}}
    <div class="lg:-mx-8 lg:-my-6 lg:h-[calc(100dvh-4rem)] lg:overflow-hidden">
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
