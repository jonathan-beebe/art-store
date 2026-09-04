<x-layouts.seller title="Listings — Art Store seller" :bleed="true">
    <div class="flex h-[calc(100dvh-4rem)] flex-col overflow-hidden">
        <x-seller.listings-header :listings-total="$listingsTotal" :chrome="$chrome" />

        <div class="min-h-0 flex-1 overflow-hidden">
            @if ($chrome->view === \App\Domain\Seller\ListingView::List)
                <x-seller.list-detail>
                    <x-slot:list>
                        <x-seller.listing-rows :listings="$listings" />
                        <x-seller.cell-footer :shown="$listings->count()" :total="$listingsTotal" />
                    </x-slot:list>

                    <div class="flex h-full items-center justify-center p-8 text-center">
                        <p class="text-gray-500 dark:text-gray-500">Choose a listing to see its details.</p>
                    </div>
                </x-seller.list-detail>
            @else
                <div class="h-full overflow-y-auto p-6 lg:p-8">
                    @if ($chrome->view === \App\Domain\Seller\ListingView::Table)
                        @include('seller.listings._table')
                    @else
                        @include('seller.listings._grid')
                    @endif
                </div>
            @endif
        </div>
    </div>
</x-layouts.seller>
