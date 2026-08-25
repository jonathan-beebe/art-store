<x-layouts.seller title="Notifications — Art Store seller">
    <h1 class="text-xl font-semibold">Notifications</h1>

    @if ($notifications->isEmpty())
        <p class="mt-4 rounded border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-900 p-4 text-gray-600 dark:text-gray-400">Nothing yet.</p>
    @else
        <ul class="mt-4 divide-y divide-gray-200 dark:divide-gray-800 rounded border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-900">
            @foreach ($notifications as $notification)
                <li class="flex flex-wrap items-start gap-4 p-4">
                    <div>
                        <p class="font-medium">
                            {{ $notification->data['subject'] }}
                            @if ($notification->read_at === null)
                                <span class="ml-1 rounded bg-gray-900 dark:bg-gray-100 px-2 py-0.5 text-xs font-medium text-white dark:text-gray-900">Unread</span>
                            @endif
                        </p>
                        <p class="mt-1 text-gray-600 dark:text-gray-400">{{ $notification->data['body'] }}</p>
                        <p class="mt-1 text-gray-500">{{ $notification->created_at->format('M j, Y g:ia') }}</p>
                        @if ($notification->data['url'])
                            <p class="mt-1"><a href="{{ $notification->data['url'] }}" class="text-gray-700 dark:text-gray-300 underline">Open</a></p>
                        @endif
                    </div>

                    @if ($notification->read_at === null)
                        <form method="POST" action="{{ route('seller.notifications.read', $notification->id) }}" class="ml-auto">
                            @csrf
                            <button type="submit" class="rounded border border-gray-400 dark:border-gray-600 px-3 py-1">Mark as read</button>
                        </form>
                    @endif
                </li>
            @endforeach
        </ul>
    @endif
</x-layouts.seller>
