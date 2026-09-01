<x-layouts.seller title="Dashboard — Art Store seller">
    <h1 class="text-xl font-semibold">Dashboard</h1>

    <section aria-labelledby="work-heading" class="mt-5">
        <h2 id="work-heading" class="sr-only">Money and work</h2>

        <div class="grid grid-cols-2 gap-px overflow-hidden rounded-lg bg-gray-200 ring-1 ring-gray-200 sm:grid-cols-4 dark:bg-white/10 dark:ring-white/10">
            <div class="bg-white p-6 dark:bg-gray-900">
                <p class="text-sm/6 font-medium text-gray-500 dark:text-gray-400">Awaiting shipment</p>
                <p class="mt-1 text-3xl font-semibold tracking-tight text-gray-900 dark:text-white">{{ $openFulfillments }}</p>
            </div>
            <div class="bg-white p-6 dark:bg-gray-900">
                <p class="text-sm/6 font-medium text-gray-500 dark:text-gray-400">Held in escrow</p>
                <p class="mt-1 text-3xl font-semibold tracking-tight text-gray-900 dark:text-white">{{ $balance->held->format() }}</p>
            </div>
            <div class="bg-white p-6 dark:bg-gray-900">
                <p class="text-sm/6 font-medium text-gray-500 dark:text-gray-400">Available</p>
                <p class="mt-1 text-3xl font-semibold tracking-tight text-gray-900 dark:text-white">{{ $balance->available->format() }}</p>
            </div>
            <div class="bg-white p-6 dark:bg-gray-900">
                <p class="text-sm/6 font-medium text-gray-500 dark:text-gray-400">Unread notifications</p>
                <p class="mt-1 text-3xl font-semibold tracking-tight text-gray-900 dark:text-white">{{ $unreadNotifications }}</p>
            </div>
        </div>
    </section>

    <section aria-labelledby="listings-heading" class="mt-8">
        <h2 id="listings-heading" class="text-sm/6 font-semibold text-gray-900 dark:text-white">Listings</h2>

        <dl class="mt-2 grid grid-cols-1 divide-y divide-gray-200 overflow-hidden rounded-lg border border-gray-200 bg-white sm:grid-cols-3 sm:divide-x sm:divide-y-0 dark:divide-white/10 dark:border-white/10 dark:bg-gray-900">
            @foreach ($tally as $row)
                <div class="px-4 py-5 sm:p-6">
                    <dt class="text-sm/6 text-gray-500 dark:text-gray-400">{{ $row->label() }}</dt>
                    <dd class="mt-1 text-xl font-semibold tracking-tight text-gray-900 tabular-nums dark:text-white">{{ $row->count }}</dd>
                </div>
            @endforeach
        </dl>
    </section>

    <section aria-labelledby="notifications-heading" class="mt-8">
        <div class="flex items-baseline justify-between">
            <h2 id="notifications-heading" class="text-sm/6 font-semibold text-gray-900 dark:text-white">Recent notifications</h2>
            <a href="{{ route('seller.notifications.index') }}" class="text-sm/6 font-semibold text-indigo-600 hover:text-indigo-500 dark:text-indigo-400 dark:hover:text-indigo-300">All notifications</a>
        </div>

        @if ($notifications->isEmpty())
            <p class="mt-2 rounded border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-900 p-4 text-gray-600 dark:text-gray-400">Nothing yet.</p>
        @else
            <ul class="mt-2 divide-y divide-gray-200 rounded-lg border border-gray-200 bg-white dark:divide-white/10 dark:border-white/10 dark:bg-gray-900">
                @foreach ($notifications as $notification)
                    <li class="p-4">
                        <p class="text-sm/6 font-semibold text-gray-900 dark:text-white">{{ $notification->data['subject'] }}</p>
                        <p class="mt-1 text-sm/6 text-gray-500 dark:text-gray-400">{{ $notification->data['body'] }}</p>
                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ $notification->created_at->format('M j, Y g:ia') }}</p>
                    </li>
                @endforeach
            </ul>
        @endif
    </section>
</x-layouts.seller>
