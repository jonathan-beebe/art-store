{{--
    Table view (04-listings.html): every column sortable by a header
    link, condensed rows leading to the listing's detail as an overlay or
    takeover (?from=table). Expects `rows`, `chrome`, `rangeDays`.
--}}
<div class="mt-2 overflow-x-auto rounded border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-900">
    <table class="w-full text-left">
        <caption class="sr-only">Every listing, with its price, stock, ranged analytics, sales, and conversion</caption>
        <thead class="border-b border-gray-300 dark:border-gray-700 bg-gray-50 dark:bg-gray-800/50">
            <tr>
                @foreach ($chrome->columnHeaders as $header)
                    <x-seller.sortable-th :header="$header" />
                @endforeach
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-200 dark:divide-gray-800">
            @forelse ($rows as $row)
                <tr>
                    <td class="px-4 py-2">
                        <a href="{{ route('seller.listings.show', ['listing' => $row->id, 'from' => 'table', 'sort' => $chrome->sort->column->value, 'dir' => $chrome->sort->direction->value, 'range' => $rangeDays]) }}" class="flex items-center gap-3">
                            <img src="{{ $row->imageUrl }}" alt="" class="size-9 flex-none rounded-md object-cover">
                            <span class="min-w-0">
                                <span class="block truncate font-semibold text-gray-900 dark:text-gray-100">{{ $row->title }}</span>
                                <span class="block truncate text-xs text-gray-500 dark:text-gray-400">{{ $row->medium ?? 'No medium' }} &middot; {{ $row->dimensions ?? 'No dimensions' }}</span>
                            </span>
                        </a>
                    </td>
                    <td class="px-4 py-2"><x-seller.status-badge :tint="$row->statusTint">{{ $row->statusLabel }}</x-seller.status-badge></td>
                    <td class="px-4 py-2 text-right tabular-nums">{{ $row->price()->format() }}</td>
                    <td class="px-4 py-2 text-right tabular-nums">{{ $row->stockLabel() }}</td>
                    <td class="px-4 py-2 text-right tabular-nums">{{ $row->views }}</td>
                    <td class="px-4 py-2 text-right tabular-nums">{{ $row->favorites }}</td>
                    <td class="px-4 py-2 text-right tabular-nums">{{ $row->cartAdds }}</td>
                    <td class="px-4 py-2 text-right tabular-nums">{{ $row->sold }}</td>
                    <td class="px-4 py-2 text-right font-semibold text-gray-900 tabular-nums dark:text-gray-100">{{ $row->revenue()->format() }}</td>
                    <td class="px-4 py-2 text-right tabular-nums">{{ $row->conversionLabel() }}</td>
                    <td class="px-4 py-2 text-right text-xs text-gray-500 tabular-nums dark:text-gray-400">{{ $row->updatedAt->format('M j, Y') }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="{{ count($chrome->columnHeaders) }}" class="px-4 py-6 text-center text-gray-500 dark:text-gray-400">No listings yet. Start with a new one.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>
<p class="mt-2 text-xs text-gray-500 dark:text-gray-500">Views, favorites, and cart adds count the last {{ $rangeDays }} days. Sold and revenue count paid orders that were not declined or refunded, all time.</p>
