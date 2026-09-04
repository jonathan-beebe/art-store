@props(['listings', 'caption', 'showSeller' => true])

@if ($listings->isEmpty())
    <x-admin.nothing>No listings.</x-admin.nothing>
@else
    <div class="mt-2 hidden overflow-x-auto rounded-md border border-stone-300 dark:border-stone-700 bg-white dark:bg-stone-900 sm:block">
        <table class="w-full text-left">
            <caption class="sr-only">{{ $caption }}</caption>
            <thead class="border-b border-stone-300 dark:border-stone-700 bg-stone-50 dark:bg-stone-800/50">
                <tr>
                    <th scope="col" class="px-4 py-2 font-semibold">Listing</th>
                    @if ($showSeller)
                        <th scope="col" class="px-4 py-2 font-semibold">Seller</th>
                    @endif
                    <th scope="col" class="px-4 py-2 font-semibold">Status</th>
                    <th scope="col" class="px-4 py-2 font-semibold">Removed</th>
                    <th scope="col" class="px-4 py-2 text-right font-semibold">Price</th>
                    <th scope="col" class="px-4 py-2 text-right font-semibold">Quantity</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-stone-200 dark:divide-stone-800">
                @foreach ($listings as $listing)
                    <tr>
                        <th scope="row" class="px-4 py-2 font-normal">
                            <a href="{{ route('admin.listings.show', $listing) }}" class="font-medium underline">{{ $listing->title }}</a>
                        </th>
                        @if ($showSeller)
                            <td class="px-4 py-2">
                                <a href="{{ route('admin.sellers.show', $listing->seller) }}" class="underline">{{ $listing->seller->displayName() }}</a>
                            </td>
                        @endif
                        <td class="px-4 py-2">{{ $listing->status->label() }}</td>
                        <td class="px-4 py-2">
                            @if ($listing->activeRemoval)
                                <span class="text-red-700 dark:text-red-400">{{ $listing->activeRemoval->kind->label() }}</span>
                            @else
                                <span class="text-stone-600 dark:text-stone-400">—</span>
                            @endif
                        </td>
                        <td class="px-4 py-2 text-right tabular-nums">{{ $listing->price()->format() }}</td>
                        <td class="px-4 py-2 text-right tabular-nums">{{ $listing->quantityLabel() }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <x-admin.card-list :caption="$caption">
        @foreach ($listings as $listing)
            <x-admin.card-row>
                <a href="{{ route('admin.listings.show', $listing) }}" class="font-medium underline">{{ $listing->title }}</a>
                <div class="flex items-center justify-between gap-3 text-stone-600 dark:text-stone-400">
                    <span>{{ $listing->status->label() }}</span>
                    <span class="tabular-nums text-stone-900 dark:text-stone-100">{{ $listing->price()->format() }}</span>
                </div>
                <div class="flex items-center justify-between gap-3 text-stone-600 dark:text-stone-400">
                    <span>
                        @if ($showSeller)
                            <a href="{{ route('admin.sellers.show', $listing->seller) }}" class="underline">{{ $listing->seller->displayName() }}</a>
                            &middot;
                        @endif
                        Qty {{ $listing->quantityLabel() }}
                    </span>
                    @if ($listing->activeRemoval)
                        <span class="text-red-700 dark:text-red-400">{{ $listing->activeRemoval->kind->label() }}</span>
                    @endif
                </div>
            </x-admin.card-row>
        @endforeach
    </x-admin.card-list>
@endif
