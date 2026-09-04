<x-layouts.seller :title="$listing->title.' — Art Store seller'" :bleed="true">
    <x-slot:mobileTitle>{{ $listing->title }}</x-slot:mobileTitle>

    <div class="flex h-[calc(100dvh-4rem)] flex-col overflow-hidden">
        <x-seller.listings-header :listings-total="$listingsTotal" :chrome="$chrome" />

        <div class="min-h-0 flex-1 overflow-hidden">
            <x-seller.list-detail mobile="detail">
                <x-slot:list>
                    <x-seller.listing-rows :listings="$cellListings" :selected="$listing" />
                    <x-seller.cell-footer :shown="$cellListings->count()" :total="$cellListingsTotal" :route="route('seller.listings.index')" />
                </x-slot:list>

                <div class="p-6 lg:h-full lg:overflow-y-auto lg:p-8">
                    {{-- Below `lg` only: the list is the screen a listing's own
                         screen pushed over, so a way back to it sits above the
                         detail content (mirrors x-admin.back-link's idiom, at
                         the `lg` breakpoint the seller chrome switches on). --}}
                    <a href="{{ route('seller.listings.index') }}" class="mb-4 inline-flex items-center gap-1.5 text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-gray-100 lg:hidden">
                        <svg width="16" height="16" viewBox="0 0 16 16" fill="none" aria-hidden="true"><path d="M10 4L6 8l4 4" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"></path></svg>
                        <span>Listings</span>
                    </a>

                    <x-seller.listing-detail :listing="$listing" :row="$row" :sales="$sales" :strip="$strip" :range-days="$rangeDays" />
                </div>
            </x-seller.list-detail>
        </div>
    </div>
</x-layouts.seller>
