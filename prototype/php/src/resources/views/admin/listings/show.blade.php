<x-layouts.admin :title="$listing->title.' — Art Store admin'">
    <div class="flex flex-wrap items-center gap-4">
        <h1 class="text-xl font-semibold">{{ $listing->title }}</h1>
        <a href="{{ route('admin.listings.index') }}" class="ml-auto text-gray-700 underline">All listings</a>
    </div>

    <dl class="mt-4 grid gap-3 rounded border border-gray-300 bg-white p-4 sm:grid-cols-2">
        <div>
            <dt class="text-gray-600">Seller</dt>
            <dd class="mt-1"><a href="{{ route('admin.sellers.show', $listing->seller) }}" class="underline">{{ $listing->seller->displayName() }}</a></dd>
        </div>
        <div>
            <dt class="text-gray-600">Status</dt>
            <dd class="mt-1">{{ $listing->status->label() }}</dd>
        </div>
        <div>
            <dt class="text-gray-600">Price</dt>
            <dd class="mt-1 tabular-nums">{{ $listing->price()->format() }}</dd>
        </div>
        <div>
            <dt class="text-gray-600">Quantity</dt>
            <dd class="mt-1 tabular-nums">{{ $listing->quantity }}</dd>
        </div>
        <div>
            <dt class="text-gray-600">Medium</dt>
            <dd class="mt-1">{{ $listing->medium ?? '—' }}</dd>
        </div>
        <div>
            <dt class="text-gray-600">Dimensions</dt>
            <dd class="mt-1">{{ $listing->dimensions ?? '—' }}</dd>
        </div>
        <div>
            <dt class="text-gray-600">Listed</dt>
            <dd class="mt-1">{{ $listing->created_at?->format('M j, Y') }}</dd>
        </div>
        <div>
            <dt class="text-gray-600">Storefront path</dt>
            <dd class="mt-1">/art/{{ $listing->slug }}</dd>
        </div>
    </dl>

    <section aria-labelledby="activity-heading" class="mt-6">
        <h2 id="activity-heading" class="font-semibold text-gray-700">Activity</h2>

        <dl class="mt-2 grid grid-cols-2 gap-3 sm:grid-cols-4">
            <div class="rounded border border-gray-300 bg-white p-4">
                <dt class="text-gray-600">Views</dt>
                <dd class="mt-1 text-2xl font-semibold tabular-nums">{{ $listing->views_count }}</dd>
            </div>
            <div class="rounded border border-gray-300 bg-white p-4">
                <dt class="text-gray-600">Favorited</dt>
                <dd class="mt-1 text-2xl font-semibold tabular-nums">{{ $listing->favorites_count }}</dd>
            </div>
            <div class="rounded border border-gray-300 bg-white p-4">
                <dt class="text-gray-600">Cart adds</dt>
                <dd class="mt-1 text-2xl font-semibold tabular-nums">{{ $listing->cart_adds_count }}</dd>
            </div>
            <div class="rounded border border-gray-300 bg-white p-4">
                <dt class="text-gray-600">Sold</dt>
                <dd class="mt-1 text-2xl font-semibold tabular-nums">{{ $sales->sum('quantity') }}</dd>
            </div>
        </dl>
    </section>

    <section aria-labelledby="sales-heading" class="mt-6">
        <h2 id="sales-heading" class="font-semibold text-gray-700">Sales</h2>

        @if ($sales->isEmpty())
            <x-admin.nothing>No sales yet.</x-admin.nothing>
        @else
            <div class="mt-2 overflow-x-auto rounded border border-gray-300 bg-white">
                <table class="w-full text-left">
                    <caption class="sr-only">Every order line this listing was sold on</caption>
                    <thead class="border-b border-gray-300 bg-gray-50">
                        <tr>
                            <th scope="col" class="px-4 py-2 font-semibold">Order</th>
                            <th scope="col" class="px-4 py-2 font-semibold">Status</th>
                            <th scope="col" class="px-4 py-2 text-right font-semibold">Quantity</th>
                            <th scope="col" class="px-4 py-2 text-right font-semibold">Unit price</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        @foreach ($sales as $sale)
                            <tr>
                                <th scope="row" class="px-4 py-2 font-normal">
                                    <a href="{{ route('admin.orders.show', $sale->order) }}" class="underline">{{ $sale->order->id }}</a>
                                </th>
                                <td class="px-4 py-2">{{ $sale->order->status->label() }}</td>
                                <td class="px-4 py-2 text-right tabular-nums">{{ $sale->quantity }}</td>
                                <td class="px-4 py-2 text-right tabular-nums">{{ $sale->unitPrice()->format() }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>
</x-layouts.admin>
