<x-layouts.seller :title="$listing- :bleed="true">title.' — Art Store seller'">
    <x-slot:mobileTitle>{{ $listing->title }}</x-slot:mobileTitle>

    <div class="h-[calc(100dvh-4rem)] overflow-hidden">
        <x-seller.list-detail mobile="detail">
            <x-slot:listHeader>
                @include('seller.listings._list-header', ['total' => $cellListingsTotal])
            </x-slot:listHeader>

            <x-slot:list>
                <x-seller.listing-rows :listings="$cellListings" :selected="$listing" />
                <x-seller.cell-footer :shown="$cellListings->count()" :total="$cellListingsTotal" />
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

                <div class="flex flex-wrap items-start justify-between gap-4">
                    <div class="flex flex-col gap-1">
                        <p class="text-xs text-gray-500 dark:text-gray-400">{{ $listing->id }} &middot; category: {{ $listing->category?->name ?? 'Uncategorized' }}</p>
                        <h1 class="flex items-center gap-3 text-xl font-semibold text-gray-900 dark:text-gray-100">
                            {{ $listing->title }}
                            <x-seller.listing-status-badge :listing="$listing" />
                        </h1>
                    </div>

                    <div class="flex shrink-0 gap-3">
                        <a href="{{ url('/art/'.$listing->slug) }}" class="inline-flex items-center rounded-md bg-white px-3 py-2 font-semibold text-gray-900 inset-ring inset-ring-gray-300 hover:bg-gray-50 dark:bg-white/10 dark:text-white dark:inset-ring-white/5 dark:hover:bg-white/20">Preview shop page</a>
                        <a href="{{ route('seller.listings.edit', $listing) }}" class="inline-flex items-center rounded-md bg-indigo-600 px-3 py-2 font-semibold text-white shadow-xs hover:bg-indigo-500">Edit in configurator</a>
                    </div>
                </div>

                @if ($listing->availableTransitionsFromEagerLoad() !== [])
                    {{--
                        The status transitions the old flat list offered
                        per row (Mark for sale / Mark archived / …) — the
                        redesigned list pane has no room for them, so a
                        listing's own screen is where they live now.
                    --}}
                    <div class="mt-4 flex flex-wrap gap-2">
                        @foreach ($listing->availableTransitionsFromEagerLoad() as $next)
                            <form method="POST" action="{{ route('seller.listings.status', $listing) }}">
                                @csrf
                                <input type="hidden" name="status" value="{{ $next->value }}">
                                <button type="submit" class="rounded-md bg-white px-3 py-2 text-xs font-semibold text-gray-900 inset-ring inset-ring-gray-300 hover:bg-gray-50 dark:bg-white/10 dark:text-white dark:inset-ring-white/5 dark:hover:bg-white/20">Mark {{ lcfirst($next->label()) }}</button>
                            </form>
                        @endforeach
                    </div>
                @endif

                @if ($listing->activeRemoval)
                    <div role="alert" class="mt-4 rounded border border-red-300 dark:border-red-900 bg-red-50 dark:bg-red-950/40 p-4 text-red-900 dark:text-red-200">
                        <p class="font-semibold">Removed from the storefront ({{ $listing->activeRemoval->kind->label() }})</p>
                        <p class="mt-1">{{ $listing->activeRemoval->reason }}</p>
                    </div>
                @endif

                <div class="mt-6 grid gap-8 lg:grid-cols-2">
                    <div class="flex aspect-[4/3] flex-col items-center justify-center gap-2 rounded-lg bg-gray-50 text-gray-400 inset-ring inset-ring-gray-200 dark:bg-white/5 dark:text-gray-500 dark:inset-ring-white/10">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true" class="size-8"><path d="m2.25 15.75 5.159-5.159a2.25 2.25 0 0 1 3.182 0l5.159 5.159m-1.5-1.5 1.409-1.409a2.25 2.25 0 0 1 3.182 0l2.909 2.909M3.75 21h16.5A1.5 1.5 0 0 0 21.75 19.5V4.5A1.5 1.5 0 0 0 20.25 3H3.75A1.5 1.5 0 0 0 2.25 4.5v15A1.5 1.5 0 0 0 3.75 21Z" stroke-linecap="round" stroke-linejoin="round"/></svg>
                        <span class="text-xs">Photos</span>
                    </div>

                    <dl class="divide-y divide-gray-200 dark:divide-white/10">
                        <div class="flex items-center justify-between gap-4 py-3">
                            <dt class="font-medium text-gray-900 dark:text-gray-100">Price</dt>
                            <dd class="text-gray-700 dark:text-gray-300">{{ $listing->price()->format() }}</dd>
                        </div>
                        <div class="flex items-center justify-between gap-4 py-3">
                            <dt class="font-medium text-gray-900 dark:text-gray-100">Stock</dt>
                            <dd class="text-gray-700 dark:text-gray-300">{{ \App\Domain\Listings\ListingStockLabel::withInStock($listing->quantity) }}</dd>
                        </div>
                        <div class="flex items-center justify-between gap-4 py-3">
                            <dt class="font-medium text-gray-900 dark:text-gray-100">Views</dt>
                            <dd class="tabular-nums text-gray-700 dark:text-gray-300">{{ $listing->views_count }}</dd>
                        </div>
                        <div class="flex items-center justify-between gap-4 py-3">
                            <dt class="font-medium text-gray-900 dark:text-gray-100">Favorites</dt>
                            <dd class="tabular-nums text-gray-700 dark:text-gray-300">{{ $listing->favorites_count }}</dd>
                        </div>
                        <div class="flex items-center justify-between gap-4 py-3">
                            <dt class="font-medium text-gray-900 dark:text-gray-100">Cart adds</dt>
                            <dd class="tabular-nums text-gray-700 dark:text-gray-300">{{ $listing->cart_adds_count }}</dd>
                        </div>
                        @if ($sales->isNotEmpty())
                            <div class="flex items-center justify-between gap-4 py-3">
                                <dt class="font-medium text-gray-900 dark:text-gray-100">Last sold</dt>
                                <dd class="text-gray-700 dark:text-gray-300">{{ $sales->first()->order->placed_at?->format('M j, Y') }} &mdash; {{ $sales->first()->unitPrice() }}</dd>
                            </div>
                        @endif
                    </dl>
                </div>

                <section aria-labelledby="daily-heading" class="mt-8">
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

                <section aria-labelledby="sales-heading" class="mt-8">
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
            </div>
        </x-seller.list-detail>
    </div>
</x-layouts.seller>
