<x-layouts.seller title="Dashboard — Art Store seller">
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <h1 class="text-xl font-semibold">Dashboard</h1>
            <p class="mt-0.5 text-gray-500 dark:text-gray-400">{{ $storeName }} · {{ $range->caption() }}</p>
        </div>

        <x-seller.segmented :links="$rangeLinks" label="Range" />
    </div>

    <section aria-labelledby="business-heading" class="mt-5">
        <h2 id="business-heading" class="sr-only">Your business</h2>

        <div class="grid grid-cols-1 gap-5 sm:grid-cols-3">
            @foreach ($tiles as $tile)
                <x-seller.brand-tile :tile="$tile" />
            @endforeach
        </div>
    </section>

    <section aria-labelledby="activity-heading" class="mt-8">
        <div class="flex items-baseline justify-between gap-4">
            <h2 id="activity-heading" class="text-sm/6 font-semibold text-gray-900 dark:text-white">Activity on your listings</h2>
            <a href="{{ route('seller.listings.index', ['view' => 'table', 'range' => $range->days]) }}" class="text-sm/6 font-semibold text-indigo-600 hover:text-indigo-500 dark:text-indigo-400 dark:hover:text-indigo-300">All listings</a>
        </div>

        <div class="mt-2 grid grid-cols-2 gap-px overflow-hidden rounded-lg bg-gray-200 ring-1 ring-gray-200 sm:grid-cols-4 dark:bg-white/10 dark:ring-white/10">
            @foreach ($activity->totals as $total)
                <x-stat-tile accent="gray" :label="$total->label">
                    <span class="flex items-baseline gap-2">
                        <span>{{ $total->figure() }}</span>
                        <x-seller.change :text="$total->change->text" :direction="$total->change->direction" />
                    </span>
                </x-stat-tile>
            @endforeach
        </div>

        <div class="mt-3 overflow-x-auto rounded border border-gray-300 bg-white dark:border-gray-700 dark:bg-gray-900">
            <table class="w-full text-left">
                <caption class="sr-only">The five listings drawing the most views, with their daily views, favorites, cart adds, and units sold</caption>
                <thead class="border-b border-gray-300 bg-gray-50 dark:border-gray-700 dark:bg-gray-800/50">
                    <tr>
                        <th scope="col" class="px-4 py-2 font-semibold">Listing</th>
                        <th scope="col" class="w-56 px-4 py-2 font-semibold">Views, last {{ $activity->stripDays }} days</th>
                        <th scope="col" class="px-4 py-2 text-right font-semibold">Views</th>
                        <th scope="col" class="px-4 py-2 text-right font-semibold">Favorites</th>
                        <th scope="col" class="px-4 py-2 text-right font-semibold">Cart adds</th>
                        <th scope="col" class="px-4 py-2 text-right font-semibold">Sold</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-gray-800">
                    @forelse ($activity->rows as $row)
                        <tr>
                            <td class="px-4 py-2">
                                <a href="{{ $row->href }}" class="flex items-center gap-3 rounded focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600">
                                    <img src="{{ $row->listing->imageUrl }}" alt="" width="40" height="40" class="size-10 flex-none rounded object-cover">
                                    <span class="min-w-0">
                                        <span class="block truncate font-semibold text-gray-900 dark:text-gray-100">{{ $row->listing->title }}</span>
                                        <span class="block truncate text-xs text-gray-500 dark:text-gray-400">{{ $row->listing->price()->format() }} · {{ $row->listing->stockLabel() }}</span>
                                    </span>
                                </a>
                            </td>
                            <td class="px-4 py-2">
                                <x-bar-strip :bars="$row->strip" :height="26" class="text-indigo-300 dark:text-indigo-400/60" />
                            </td>
                            <td class="px-4 py-2 text-right tabular-nums">{{ $row->listing->views }}</td>
                            <td class="px-4 py-2 text-right tabular-nums">{{ $row->listing->favorites }}</td>
                            <td class="px-4 py-2 text-right tabular-nums">{{ $row->listing->cartAdds }}</td>
                            <td class="px-4 py-2 text-right tabular-nums">{{ $row->sold }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-4 py-4 text-gray-500 dark:text-gray-400">You have no listings yet, so there is nothing for buyers to look at.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    <section aria-labelledby="attention-heading" class="mt-8">
        <h2 id="attention-heading" class="text-sm/6 font-semibold text-gray-900 dark:text-white">Needs your attention</h2>

        <div class="mt-2 grid grid-cols-1 gap-5 lg:grid-cols-2">
            @foreach ($attention as $group)
                <x-seller.attention-panel :group="$group" />
            @endforeach
        </div>
    </section>
</x-layouts.seller>
