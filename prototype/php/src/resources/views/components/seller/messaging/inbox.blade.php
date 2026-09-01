{{--
    The seller inbox's rows, shared by the messages index (nothing selected)
    and show (`selected` marks the open thread) screens, so both render the
    exact same list — the list-detail pane on the left and, below `lg`, the
    whole screen.
--}}
@props(['conversations', 'viewer', 'showRoute', 'selected' => null, 'total' => null, 'indexRoute' => null])

@if ($conversations->isEmpty())
    <p class="p-6 text-sm text-gray-500 dark:text-gray-500">Nothing yet.</p>
@else
    <ul role="list" class="divide-y divide-gray-100 dark:divide-white/5">
        @foreach ($conversations as $conversation)
            @php
                $isSelected = $selected !== null && $selected->id === $conversation->id;
                $isUnread = $conversation->unread_count > 0;
            @endphp
            <li class="relative">
                <a
                    href="{{ route($showRoute, $conversation) }}"
                    @if ($isSelected) aria-current="true" @endif
                    class="block px-6 py-4 hover:bg-gray-50 dark:hover:bg-white/5 {{ $isSelected ? 'bg-gray-50 dark:bg-white/5' : '' }}"
                >
                    @if ($isSelected)
                        <span class="absolute inset-y-0 left-0 w-0.5 bg-indigo-600" aria-hidden="true"></span>
                    @endif

                    <p class="flex min-w-0 items-center gap-x-1.5 truncate text-sm {{ $isUnread ? 'font-semibold text-gray-900 dark:text-white' : 'font-medium text-gray-700 dark:text-gray-300' }}">
                        @if ($isUnread)
                            <span class="size-1.5 shrink-0 rounded-full bg-indigo-500" aria-hidden="true"></span>
                            <span class="sr-only">{{ $conversation->unread_count }} unread</span>
                        @endif
                        <span class="truncate">{{ $conversation->counterpartName($viewer) }} &middot; {{ $conversation->kind->topic($conversation->fulfillment?->order_id, $conversation->listing?->title) }}</span>
                    </p>

                    <p class="mt-1 truncate text-xs text-gray-500 dark:text-gray-400">
                        @if ($conversation->latestMessage){{ str($conversation->latestMessage->body)->limit(80) }} &middot; @endif{{ $conversation->last_message_at?->format('M j, Y g:ia') }}
                    </p>
                </a>
            </li>
        @endforeach
    </ul>

    @if ($total !== null && $indexRoute !== null)
        <x-seller.messaging.list-footer :shown="$conversations->count()" :total="$total" :route="route($indexRoute)" />
    @endif
@endif
