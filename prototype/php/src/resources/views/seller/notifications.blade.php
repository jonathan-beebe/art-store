<x-layouts.seller title="Notifications — Art Store seller">
    <h1 class="text-xl font-semibold text-gray-900 dark:text-white">Notifications</h1>

    @if ($notifications->isEmpty())
        <p class="mt-4 text-sm text-gray-500 dark:text-gray-500">Nothing yet.</p>
    @else
        <ul role="list" class="mt-6 divide-y divide-gray-100 dark:divide-white/5">
            @foreach ($notifications as $notification)
                @php($isUnread = $notification->read_at === null)
                <li class="relative flex flex-wrap items-start gap-x-4 gap-y-3 py-5 {{ $isUnread ? 'pl-4' : '' }}">
                    @if ($isUnread)
                        <span class="absolute inset-y-0 left-0 w-0.5 bg-indigo-600" aria-hidden="true"></span>
                    @endif

                    <div class="min-w-0 flex-1">
                        <p class="flex items-center gap-x-1.5 text-sm {{ $isUnread ? 'font-semibold text-gray-900 dark:text-white' : 'font-medium text-gray-700 dark:text-gray-300' }}">
                            @if ($isUnread)
                                <span class="size-1.5 shrink-0 rounded-full bg-indigo-500" aria-hidden="true"></span>
                                <span class="sr-only">Unread:</span>
                            @endif
                            {{ $notification->data['subject'] }}
                        </p>
                        <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">{{ $notification->data['body'] }}</p>
                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-500">{{ $notification->created_at->format('M j, Y g:ia') }}</p>
                        @if ($notification->data['url'])
                            <p class="mt-1"><a href="{{ $notification->data['url'] }}" class="text-sm text-indigo-600 hover:text-indigo-500 dark:text-indigo-400 dark:hover:text-indigo-300">Open</a></p>
                        @endif
                    </div>

                    @if ($isUnread)
                        <form method="POST" action="{{ route('seller.notifications.read', $notification->id) }}" class="shrink-0">
                            @csrf
                            <button type="submit" class="rounded-full bg-white px-2.5 py-1 text-xs font-semibold text-gray-900 shadow-xs inset-ring inset-ring-gray-300 hover:bg-gray-50 dark:bg-white/10 dark:text-white dark:shadow-none dark:inset-ring-white/5 dark:hover:bg-white/20">Mark as read</button>
                        </form>
                    @endif
                </li>
            @endforeach
        </ul>
    @endif
</x-layouts.seller>
