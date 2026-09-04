<x-layouts.seller :title="$row->name.' — Art Store seller'">
    <a href="{{ route('seller.customers.index') }}" class="inline-flex min-h-11 items-center gap-1.5 text-sm font-medium text-gray-600 hover:text-gray-900 dark:text-gray-400 dark:hover:text-gray-100">
        <svg width="16" height="16" viewBox="0 0 16 16" fill="none" aria-hidden="true"><path d="M10 4L6 8l4 4" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" /></svg>
        <span>Customers</span>
    </a>

    <div class="mt-3 flex flex-wrap items-start justify-between gap-4">
        <div class="flex items-center gap-4">
            <span aria-hidden="true" class="flex size-14 flex-none items-center justify-center rounded-full bg-indigo-50 text-lg font-semibold text-indigo-700 dark:bg-indigo-500/10 dark:text-indigo-300">{{ $row->initials() }}</span>
            <div class="min-w-0">
                <h1 class="flex items-center gap-3 text-xl font-semibold">
                    {{ $row->name }}
                    @if ($row->isRepeatBuyer())
                        <x-seller.status-badge tint="blue">Repeat buyer</x-seller.status-badge>
                    @endif
                </h1>
                <p class="text-gray-500 dark:text-gray-400">{{ $row->email ?? 'No email' }} &middot; customer since {{ $row->firstOrderAt->format('F j, Y') }}</p>
            </div>
        </div>

        <form method="POST" action="{{ route('seller.customers.messages', $customer) }}">
            @csrf
            <button type="submit" class="rounded-md bg-white px-3 py-2 text-sm font-semibold text-gray-900 shadow-xs inset-ring inset-ring-gray-300 hover:bg-gray-50 dark:bg-white/10 dark:text-white dark:shadow-none dark:inset-ring-white/10 dark:hover:bg-white/20">Message</button>
        </form>
    </div>

    <div class="mt-5 grid grid-cols-2 gap-px overflow-hidden rounded-lg bg-gray-200 ring-1 ring-gray-200 sm:grid-cols-4 dark:bg-white/10 dark:ring-white/10">
        <x-stat-tile accent="gray" label="Orders">{{ $row->orders }}</x-stat-tile>
        <x-stat-tile accent="gray" label="Spent with you">{{ $row->spent()->format() }}</x-stat-tile>
        <x-stat-tile accent="gray" label="Favorites">{{ $row->favorites }}</x-stat-tile>
        <x-stat-tile accent="gray" label="Conversations">{{ $row->conversations }}</x-stat-tile>
    </div>

    <div class="mt-6 grid items-start gap-6 lg:grid-cols-[minmax(0,1fr)_22rem]">
        <div>
            <div class="flex flex-wrap items-center justify-between gap-4">
                <h2 class="text-sm/6 font-semibold text-gray-900 dark:text-white">Timeline</h2>
                <x-seller.segmented :links="$feedFilters" label="Activity kind" />
            </div>

            <div class="mt-2 rounded-lg border border-gray-200 bg-white p-6 dark:border-white/10 dark:bg-gray-900">
                <x-seller.feed :feed="$feed" empty="Nothing has passed between the two of you yet." />
            </div>
        </div>

        <div class="flex flex-col gap-6">
            <div>
                <h2 class="text-sm/6 font-semibold text-gray-900 dark:text-white">Orders</h2>
                <ul role="list" class="mt-2 divide-y divide-gray-100 overflow-hidden rounded-lg border border-gray-200 bg-white dark:divide-white/5 dark:border-white/10 dark:bg-gray-900">
                    @forelse ($fulfillments as $fulfillment)
                        <li>
                            <a href="{{ route('seller.orders.show', $fulfillment) }}" class="flex items-center gap-3 px-4 py-3 hover:bg-gray-50 focus-visible:outline-2 focus-visible:-outline-offset-2 focus-visible:outline-indigo-600 dark:hover:bg-white/5">
                                <img src="{{ $fulfillment->order->items->first()?->listing?->imageUrl() ?? \App\Support\PlaceholderImage::dataUri($fulfillment->itemLabel()) }}" alt="" class="size-10 flex-none rounded-md object-cover">
                                <span class="min-w-0 flex-1">
                                    <span class="block truncate font-medium text-gray-900 dark:text-gray-100">{{ $fulfillment->itemLabel() }}</span>
                                    <span class="block text-xs/5 text-gray-500 dark:text-gray-400">{{ $fulfillment->order->placed_at?->format('M j, Y') }} &middot; {{ $fulfillment->subtotal()->format() }}</span>
                                </span>
                                <x-seller.status-badge :tint="$fulfillment->status->sellerBadgeTint()">{{ $fulfillment->status->label() }}</x-seller.status-badge>
                            </a>
                        </li>
                    @empty
                        <li class="px-4 py-3 text-sm text-gray-500 dark:text-gray-400">No orders.</li>
                    @endforelse
                </ul>
            </div>

            <div>
                <h2 class="text-sm/6 font-semibold text-gray-900 dark:text-white">Favorites</h2>
                @if ($favorites->isEmpty())
                    <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">None of your pieces yet.</p>
                @else
                    <ul role="list" class="mt-2 grid grid-cols-4 gap-2">
                        @foreach ($favorites as $favorite)
                            <li>
                                <a href="{{ route('seller.listings.show', $favorite) }}" class="block rounded-md focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600">
                                    <img src="{{ $favorite->imageUrl() }}" alt="{{ $favorite->title }}" class="aspect-square w-full rounded-md object-cover">
                                </a>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>

            <div>
                <h2 class="text-sm/6 font-semibold text-gray-900 dark:text-white">Conversations</h2>
                <ul role="list" class="mt-2 divide-y divide-gray-100 overflow-hidden rounded-lg border border-gray-200 bg-white dark:divide-white/5 dark:border-white/10 dark:bg-gray-900">
                    @forelse ($conversations as $conversation)
                        <li>
                            <a href="{{ route('seller.messages.show', $conversation) }}" class="flex items-center gap-3 px-4 py-3 hover:bg-gray-50 focus-visible:outline-2 focus-visible:-outline-offset-2 focus-visible:outline-indigo-600 dark:hover:bg-white/5">
                                <x-seller.thread-tag :kind="$conversation->kind" />
                                <span class="min-w-0 flex-1 truncate font-medium text-gray-900 dark:text-gray-100">{{ $conversation->title ?? $conversation->kind->topic($conversation->fulfillment?->order_id, $conversation->listing?->title) }}</span>
                                <span class="shrink-0 text-xs/5 text-gray-500 dark:text-gray-400">{{ $conversation->last_message_at === null ? '' : \App\Domain\Support\RelativeTime::short($conversation->last_message_at, $now) }}</span>
                            </a>
                        </li>
                    @empty
                        <li class="px-4 py-3 text-sm text-gray-500 dark:text-gray-400">No conversations yet.</li>
                    @endforelse
                </ul>
            </div>
        </div>
    </div>
</x-layouts.seller>
