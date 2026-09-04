<x-layouts.seller title="Customers — Art Store seller">
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <h1 class="text-xl font-semibold">Customers</h1>
            <p class="mt-0.5 text-gray-500 dark:text-gray-400">Everyone who has bought from you. Browsing and favorites show up on each person's timeline.</p>
        </div>

        <x-seller.segmented :links="$chrome->segments" label="Segment" />
    </div>

    <div class="mt-5 grid grid-cols-2 gap-px overflow-hidden rounded-lg bg-gray-200 ring-1 ring-gray-200 sm:grid-cols-4 dark:bg-white/10 dark:ring-white/10">
        <x-stat-tile accent="gray" label="Customers">
            <span class="flex items-baseline gap-2">
                <span data-stat="customers">{{ $tally->customers }}</span>
                <span data-stat="customers-new" class="text-sm font-medium text-green-600 dark:text-green-400">+{{ $tally->newThisPeriod }} new</span>
            </span>
        </x-stat-tile>
        <x-stat-tile accent="gray" label="Repeat buyers">
            <span class="flex items-baseline gap-2">
                <span data-stat="repeat-buyers">{{ $tally->repeatBuyers }}</span>
                <span data-stat="repeat-share" class="text-sm font-medium text-gray-500 dark:text-gray-400">{{ $tally->repeatShare() === null ? '—' : $tally->repeatShare().'%' }}</span>
            </span>
        </x-stat-tile>
        <x-stat-tile accent="gray" label="Average order"><span data-stat="average-order">{{ $tally->averageOrder()?->format() ?? '—' }}</span></x-stat-tile>
        <x-stat-tile accent="gray" label="Open conversations">
            <span class="flex items-baseline gap-2">
                <span data-stat="open-conversations">{{ $tally->openConversations }}</span>
                <span data-stat="unread-conversations" class="text-sm font-medium text-indigo-600 dark:text-indigo-400">{{ $tally->unreadConversations }} unread</span>
            </span>
        </x-stat-tile>
    </div>

    <div class="mt-5 overflow-x-auto rounded border border-gray-300 bg-white dark:border-gray-700 dark:bg-gray-900">
        <table class="w-full text-left">
            <caption class="sr-only">Every buyer, with their orders, spend, favorites, last order, conversations, and first order</caption>
            <thead class="border-b border-gray-300 bg-gray-50 dark:border-gray-700 dark:bg-gray-800/50">
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
                            <a href="{{ route('seller.customers.show', $row->customerId) }}" class="flex items-center gap-3 rounded focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600">
                                <span aria-hidden="true" class="flex size-9 flex-none items-center justify-center rounded-full bg-indigo-50 text-xs font-semibold text-indigo-700 dark:bg-indigo-500/10 dark:text-indigo-300">{{ $row->initials() }}</span>
                                <span class="min-w-0">
                                    <span class="block truncate font-semibold text-gray-900 dark:text-gray-100">{{ $row->name }}</span>
                                    <span class="block truncate text-xs text-gray-500 dark:text-gray-400">{{ $row->email ?? 'No email' }}</span>
                                </span>
                            </a>
                        </td>
                        <td class="px-4 py-2 text-right tabular-nums">{{ $row->orders }}</td>
                        <td class="px-4 py-2 text-right font-semibold text-gray-900 tabular-nums dark:text-gray-100">{{ $row->spent()->format() }}</td>
                        <td class="px-4 py-2 text-right tabular-nums">{{ $row->favorites }}</td>
                        <td class="px-4 py-2 tabular-nums">{{ $row->lastOrderAt->format('M j, Y') }}</td>
                        <td class="px-4 py-2 text-right tabular-nums">{{ $row->conversations }}</td>
                        <td class="px-4 py-2 text-xs text-gray-500 tabular-nums dark:text-gray-400">{{ $row->firstOrderAt->format('M j, Y') }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="{{ count($chrome->columnHeaders) }}" class="px-4 py-6 text-center text-gray-500 dark:text-gray-400">No customers here yet. A live order is what makes someone a customer.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <p class="mt-2 text-xs text-gray-500 dark:text-gray-500">Repeat buyers have ordered twice or more. New counts a first order inside the last {{ $rangeDays }} days. A declined or refunded order counts for nothing here.</p>
</x-layouts.seller>
