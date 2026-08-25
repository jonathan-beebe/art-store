<x-layouts.seller title="Dashboard — Art Store seller">
    <h1 class="text-xl font-semibold">Dashboard</h1>

    <section aria-labelledby="listings-heading" class="mt-6">
        <h2 id="listings-heading" class="font-semibold text-gray-700 dark:text-gray-300">Listings</h2>

        <dl class="mt-2 grid grid-cols-2 gap-3 sm:grid-cols-4">
            @foreach ($tally as $row)
                <div class="rounded border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-900 p-4">
                    <dt class="text-gray-600 dark:text-gray-400">{{ $row->label() }}</dt>
                    <dd class="mt-1 text-2xl font-semibold tabular-nums">{{ $row->count }}</dd>
                </div>
            @endforeach
        </dl>
    </section>

    <section aria-labelledby="work-heading" class="mt-6">
        <h2 id="work-heading" class="font-semibold text-gray-700 dark:text-gray-300">Money and work</h2>

        <dl class="mt-2 grid grid-cols-2 gap-3 sm:grid-cols-4">
            <div class="rounded border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-900 p-4">
                <dt class="text-gray-600 dark:text-gray-400">Awaiting shipment</dt>
                <dd class="mt-1 text-2xl font-semibold tabular-nums">{{ $openFulfillments }}</dd>
            </div>
            <div class="rounded border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-900 p-4">
                <dt class="text-gray-600 dark:text-gray-400">Held in escrow</dt>
                <dd class="mt-1 text-2xl font-semibold tabular-nums">{{ $balance->held->format() }}</dd>
            </div>
            <div class="rounded border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-900 p-4">
                <dt class="text-gray-600 dark:text-gray-400">Available</dt>
                <dd class="mt-1 text-2xl font-semibold tabular-nums">{{ $balance->available->format() }}</dd>
            </div>
            <div class="rounded border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-900 p-4">
                <dt class="text-gray-600 dark:text-gray-400">Unread notifications</dt>
                <dd class="mt-1 text-2xl font-semibold tabular-nums">{{ $unreadNotifications }}</dd>
            </div>
        </dl>
    </section>

    <section aria-labelledby="notifications-heading" class="mt-6">
        <h2 id="notifications-heading" class="font-semibold text-gray-700 dark:text-gray-300">Recent notifications</h2>

        @if ($notifications->isEmpty())
            <p class="mt-2 rounded border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-900 p-4 text-gray-600 dark:text-gray-400">Nothing yet.</p>
        @else
            <ul class="mt-2 divide-y divide-gray-200 dark:divide-gray-800 rounded border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-900">
                @foreach ($notifications as $notification)
                    <li class="p-4">
                        <p class="font-medium">{{ $notification->data['subject'] }}</p>
                        <p class="mt-1 text-gray-600 dark:text-gray-400">{{ $notification->data['body'] }}</p>
                        <p class="mt-1 text-gray-500">{{ $notification->created_at->format('M j, Y g:ia') }}</p>
                    </li>
                @endforeach
            </ul>

            <p class="mt-2"><a href="{{ route('seller.notifications.index') }}" class="text-gray-700 dark:text-gray-300 underline">All notifications</a></p>
        @endif
    </section>
</x-layouts.seller>
