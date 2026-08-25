<x-layouts.admin :title="$listing->title.' — Art Store admin'">
    <div class="flex flex-wrap items-center gap-4">
        <h1 class="text-xl font-semibold">{{ $listing->title }}</h1>
        <a href="{{ route('admin.listings.index') }}" class="ml-auto text-gray-700 dark:text-gray-300 underline">All listings</a>
    </div>

    <dl class="mt-4 grid gap-3 rounded border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-900 p-4 sm:grid-cols-2">
        <div>
            <dt class="text-gray-600 dark:text-gray-400">Seller</dt>
            <dd class="mt-1"><a href="{{ route('admin.sellers.show', $listing->seller) }}" class="underline">{{ $listing->seller->displayName() }}</a></dd>
        </div>
        <div>
            <dt class="text-gray-600 dark:text-gray-400">Status</dt>
            <dd class="mt-1">{{ $listing->status->label() }}</dd>
        </div>
        <div>
            <dt class="text-gray-600 dark:text-gray-400">Price</dt>
            <dd class="mt-1 tabular-nums">{{ $listing->price()->format() }}</dd>
        </div>
        <div>
            <dt class="text-gray-600 dark:text-gray-400">Quantity</dt>
            <dd class="mt-1 tabular-nums">{{ $listing->quantity }}</dd>
        </div>
        <div>
            <dt class="text-gray-600 dark:text-gray-400">Medium</dt>
            <dd class="mt-1">{{ $listing->medium ?? '—' }}</dd>
        </div>
        <div>
            <dt class="text-gray-600 dark:text-gray-400">Dimensions</dt>
            <dd class="mt-1">{{ $listing->dimensions ?? '—' }}</dd>
        </div>
        <div>
            <dt class="text-gray-600 dark:text-gray-400">Listed</dt>
            <dd class="mt-1">{{ $listing->created_at?->format('M j, Y') }}</dd>
        </div>
        <div>
            <dt class="text-gray-600 dark:text-gray-400">Storefront path</dt>
            <dd class="mt-1">/art/{{ $listing->slug }}</dd>
        </div>
    </dl>

    <section aria-labelledby="moderation-heading" class="mt-6 max-w-xl">
        <h2 id="moderation-heading" class="font-semibold text-gray-700 dark:text-gray-300">Storefront</h2>

        @if ($listing->activeRemoval)
            <dl class="mt-2 rounded border border-red-300 dark:border-red-900 bg-red-50 dark:bg-red-950/40 p-4 text-red-900 dark:text-red-200">
                <dt class="font-semibold">Removed ({{ $listing->activeRemoval->kind->label() }})</dt>
                <dd class="mt-1">{{ $listing->activeRemoval->reason }}</dd>
                <dd class="mt-1 text-red-700 dark:text-red-400">Since {{ $listing->activeRemoval->created_at?->format('M j, Y g:ia') }}</dd>
            </dl>

            @if ($listing->activeRemoval->kind->canLift())
                <form method="POST" action="{{ route('admin.listings.removals.lift', $listing) }}" class="mt-2">
                    @csrf
                    <button type="submit" class="rounded bg-gray-900 dark:bg-gray-100 px-4 py-2 font-medium text-white dark:text-gray-900">Lift removal</button>
                </form>
            @endif
        @else
            <p class="mt-2 rounded border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-900 p-4 text-gray-600 dark:text-gray-400">On the storefront.</p>

            <form method="POST" action="{{ route('admin.listings.removals.store', $listing) }}"
                  class="mt-2 rounded border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-900 p-4">
                @csrf

                <label for="kind" class="block font-medium text-gray-700 dark:text-gray-300">Kind</label>
                <select id="kind" name="kind" class="mt-1 block w-full rounded border border-gray-400 dark:border-gray-600 px-3 py-2">
                    <option value="temporary">Temporary</option>
                    <option value="permanent">Permanent</option>
                </select>
                @error('kind')
                    <p class="mt-1 text-red-700 dark:text-red-400">{{ $message }}</p>
                @enderror

                <label for="reason" class="mt-3 block font-medium text-gray-700 dark:text-gray-300">Reason</label>
                <input id="reason" name="reason" type="text" required maxlength="500" value="{{ old('reason') }}"
                       class="mt-1 block w-full rounded border border-gray-400 dark:border-gray-600 px-3 py-2">
                @error('reason')
                    <p class="mt-1 text-red-700 dark:text-red-400">{{ $message }}</p>
                @enderror

                <button type="submit" class="mt-4 rounded bg-gray-900 dark:bg-gray-100 px-4 py-2 font-medium text-white dark:text-gray-900">Remove from storefront</button>
            </form>
        @endif
    </section>

    <section aria-labelledby="removal-history-heading" class="mt-6">
        <h2 id="removal-history-heading" class="font-semibold text-gray-700 dark:text-gray-300">Removal history</h2>

        @if ($removals->isEmpty())
            <x-admin.nothing>Never removed.</x-admin.nothing>
        @else
            <div class="mt-2 overflow-x-auto rounded border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-900">
                <table class="w-full text-left">
                    <caption class="sr-only">Every removal this listing has been under</caption>
                    <thead class="border-b border-gray-300 dark:border-gray-700 bg-gray-50 dark:bg-gray-800/50">
                        <tr>
                            <th scope="col" class="px-4 py-2 font-semibold">Kind</th>
                            <th scope="col" class="px-4 py-2 font-semibold">Reason</th>
                            <th scope="col" class="px-4 py-2 font-semibold">Removed</th>
                            <th scope="col" class="px-4 py-2 font-semibold">Lifted</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-gray-800">
                        @foreach ($removals as $removal)
                            <tr>
                                <th scope="row" class="px-4 py-2 font-normal">{{ $removal->kind->label() }}</th>
                                <td class="px-4 py-2">{{ $removal->reason }}</td>
                                <td class="px-4 py-2">{{ $removal->created_at?->format('M j, Y g:ia') }}</td>
                                <td class="px-4 py-2">{{ $removal->lifted_at?->format('M j, Y g:ia') ?? 'Active' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>

    <section aria-labelledby="activity-heading" class="mt-6">
        <h2 id="activity-heading" class="font-semibold text-gray-700 dark:text-gray-300">Activity</h2>

        <dl class="mt-2 grid grid-cols-2 gap-3 sm:grid-cols-4">
            <div class="rounded border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-900 p-4">
                <dt class="text-gray-600 dark:text-gray-400">Views</dt>
                <dd class="mt-1 text-2xl font-semibold tabular-nums">{{ $listing->views_count }}</dd>
            </div>
            <div class="rounded border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-900 p-4">
                <dt class="text-gray-600 dark:text-gray-400">Favorited</dt>
                <dd class="mt-1 text-2xl font-semibold tabular-nums">{{ $listing->favorites_count }}</dd>
            </div>
            <div class="rounded border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-900 p-4">
                <dt class="text-gray-600 dark:text-gray-400">Cart adds</dt>
                <dd class="mt-1 text-2xl font-semibold tabular-nums">{{ $listing->cart_adds_count }}</dd>
            </div>
            <div class="rounded border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-900 p-4">
                <dt class="text-gray-600 dark:text-gray-400">Sold</dt>
                <dd class="mt-1 text-2xl font-semibold tabular-nums">{{ $sales->sum('quantity') }}</dd>
            </div>
        </dl>
    </section>

    <section aria-labelledby="sales-heading" class="mt-6">
        <h2 id="sales-heading" class="font-semibold text-gray-700 dark:text-gray-300">Sales</h2>

        @if ($sales->isEmpty())
            <x-admin.nothing>No sales yet.</x-admin.nothing>
        @else
            <div class="mt-2 overflow-x-auto rounded border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-900">
                <table class="w-full text-left">
                    <caption class="sr-only">Every order line this listing was sold on</caption>
                    <thead class="border-b border-gray-300 dark:border-gray-700 bg-gray-50 dark:bg-gray-800/50">
                        <tr>
                            <th scope="col" class="px-4 py-2 font-semibold">Order</th>
                            <th scope="col" class="px-4 py-2 font-semibold">Status</th>
                            <th scope="col" class="px-4 py-2 text-right font-semibold">Quantity</th>
                            <th scope="col" class="px-4 py-2 text-right font-semibold">Unit price</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-gray-800">
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
