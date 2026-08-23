<x-layouts.seller title="Notifications — Art Store seller">
    <h1 class="text-xl font-semibold">Notifications</h1>

    @if ($notifications->isEmpty())
        <p class="mt-4 rounded border border-gray-300 bg-white p-4 text-gray-600">Nothing yet.</p>
    @else
        <ul class="mt-4 divide-y divide-gray-200 rounded border border-gray-300 bg-white">
            @foreach ($notifications as $notification)
                <li class="flex flex-wrap items-start gap-4 p-4">
                    <div>
                        <p class="font-medium">
                            {{ $notification->data['subject'] }}
                            @if ($notification->read_at === null)
                                <span class="ml-1 rounded bg-gray-900 px-2 py-0.5 text-xs font-medium text-white">Unread</span>
                            @endif
                        </p>
                        <p class="mt-1 text-gray-600">{{ $notification->data['body'] }}</p>
                        <p class="mt-1 text-gray-500">{{ $notification->created_at->format('M j, Y g:ia') }}</p>
                        @if ($notification->data['url'])
                            <p class="mt-1"><a href="{{ $notification->data['url'] }}" class="text-gray-700 underline">Open</a></p>
                        @endif
                    </div>

                    @if ($notification->read_at === null)
                        <form method="POST" action="{{ route('seller.notifications.read', $notification->id) }}" class="ml-auto">
                            @csrf
                            <button type="submit" class="rounded border border-gray-400 px-3 py-1">Mark as read</button>
                        </form>
                    @endif
                </li>
            @endforeach
        </ul>
    @endif
</x-layouts.seller>
