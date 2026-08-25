<x-layouts.seller :title="$listing->title.' — Art Store seller'">
    <div class="flex flex-wrap items-center gap-4">
        <h1 class="text-xl font-semibold">{{ $listing->title }}</h1>
        <p class="text-gray-600 dark:text-gray-400">{{ $listing->status->label() }} · {{ $listing->price()->format() }} · {{ $listing->quantity }} in stock</p>
        <a href="{{ route('seller.listings.faqs.index', $listing) }}" class="ml-auto text-gray-700 dark:text-gray-300 underline">Questions & answers</a>
        <a href="{{ route('seller.listings.edit', $listing->id) }}" class="rounded border border-gray-400 dark:border-gray-600 px-3 py-2">Edit</a>
    </div>

    @if ($listing->activeRemoval)
        <div role="alert" class="mt-4 rounded border border-red-300 dark:border-red-900 bg-red-50 dark:bg-red-950/40 p-4 text-red-900 dark:text-red-200">
            <p class="font-semibold">Removed from the storefront ({{ $listing->activeRemoval->kind->label() }})</p>
            <p class="mt-1">{{ $listing->activeRemoval->reason }}</p>
        </div>
    @endif

    <section aria-labelledby="totals-heading" class="mt-6">
        <h2 id="totals-heading" class="font-semibold text-gray-700 dark:text-gray-300">Totals</h2>

        <dl class="mt-2 grid grid-cols-3 gap-3">
            <div class="rounded border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-900 p-4">
                <dt class="text-gray-600 dark:text-gray-400">Views</dt>
                <dd class="mt-1 text-2xl font-semibold tabular-nums">{{ $listing->views_count }}</dd>
            </div>
            <div class="rounded border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-900 p-4">
                <dt class="text-gray-600 dark:text-gray-400">Favorites</dt>
                <dd class="mt-1 text-2xl font-semibold tabular-nums">{{ $listing->favorites_count }}</dd>
            </div>
            <div class="rounded border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-900 p-4">
                <dt class="text-gray-600 dark:text-gray-400">Cart adds</dt>
                <dd class="mt-1 text-2xl font-semibold tabular-nums">{{ $listing->cart_adds_count }}</dd>
            </div>
        </dl>
    </section>

    <section aria-labelledby="daily-heading" class="mt-6">
        <h2 id="daily-heading" class="font-semibold text-gray-700 dark:text-gray-300">Last {{ $windowDays }} days</h2>

        <div class="mt-2 overflow-x-auto rounded border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-900">
            <table class="w-full text-left">
                <caption class="sr-only">Daily views, favorites, and cart adds</caption>
                <thead class="border-b border-gray-300 dark:border-gray-700 bg-gray-50 dark:bg-gray-800/50">
                    <tr>
                        <th scope="col" class="px-4 py-2 font-semibold">Day</th>
                        <th scope="col" class="px-4 py-2 text-right font-semibold">Views</th>
                        <th scope="col" class="px-4 py-2 text-right font-semibold">Favorites</th>
                        <th scope="col" class="px-4 py-2 text-right font-semibold">Cart adds</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-gray-800">
                    @foreach ($days as $day)
                        <tr>
                            <th scope="row" class="px-4 py-2 font-normal">{{ $day->label() }}</th>
                            <td class="px-4 py-2 text-right tabular-nums">{{ $day->views }}</td>
                            <td class="px-4 py-2 text-right tabular-nums">{{ $day->favorites }}</td>
                            <td class="px-4 py-2 text-right tabular-nums">{{ $day->cartAdds }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </section>

    <section aria-labelledby="sales-heading" class="mt-6">
        <h2 id="sales-heading" class="font-semibold text-gray-700 dark:text-gray-300">Sales</h2>

        @if ($sales->isEmpty())
            <p class="mt-2 rounded border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-900 p-4 text-gray-600 dark:text-gray-400">No sales yet.</p>
        @else
            <div class="mt-2 overflow-x-auto rounded border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-900">
                <table class="w-full text-left">
                    <caption class="sr-only">Orders containing this listing</caption>
                    <thead class="border-b border-gray-300 dark:border-gray-700 bg-gray-50 dark:bg-gray-800/50">
                        <tr>
                            <th scope="col" class="px-4 py-2 font-semibold">Order</th>
                            <th scope="col" class="px-4 py-2 font-semibold">Placed</th>
                            <th scope="col" class="px-4 py-2 font-semibold">Status</th>
                            <th scope="col" class="px-4 py-2 text-right font-semibold">Qty</th>
                            <th scope="col" class="px-4 py-2 text-right font-semibold">Unit price</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-gray-800">
                        @foreach ($sales as $sale)
                            <tr>
                                <th scope="row" class="px-4 py-2 font-normal">{{ $sale->order_id }}</th>
                                <td class="px-4 py-2">{{ $sale->order->placed_at?->format('M j, Y') }}</td>
                                <td class="px-4 py-2">{{ $sale->order->status->label() }}</td>
                                <td class="px-4 py-2 text-right tabular-nums">{{ $sale->quantity }}</td>
                                <td class="px-4 py-2 text-right tabular-nums">{{ $sale->unitPrice() }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>
</x-layouts.seller>
