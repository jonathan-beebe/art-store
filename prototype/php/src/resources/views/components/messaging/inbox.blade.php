{{-- `selected` (DSGN-006) marks the conversation the admin has open beside
     this list — unused, so unaffected, everywhere else this component is
     shared (the seller inbox). --}}
@props(['conversations', 'viewer', 'showRoute', 'selected' => null])

@if ($conversations->isEmpty())
    <p class="mt-4 rounded border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-900 p-4 text-gray-600 dark:text-gray-400">Nothing yet.</p>
@else
    <ul class="mt-4 divide-y divide-gray-200 dark:divide-gray-800 rounded border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-900">
        @foreach ($conversations as $conversation)
            @php($isSelected = $selected !== null && $selected->id === $conversation->id)
            <li>
                <a
                    href="{{ route($showRoute, $conversation) }}"
                    @if ($isSelected) aria-current="true" @endif
                    class="flex flex-wrap items-start gap-4 p-4 hover:bg-gray-50 dark:hover:bg-gray-800/50 {{ $isSelected ? 'bg-gray-100 dark:bg-gray-800' : '' }}"
                >
                    <div>
                        <p class="font-medium">
                            {{ $conversation->counterpartName($viewer) }}
                            @if ($conversation->unread_count > 0)
                                <span class="ml-1 rounded bg-gray-900 dark:bg-gray-100 px-2 py-0.5 text-xs font-medium text-white dark:text-gray-900">{{ $conversation->unread_count }} unread</span>
                            @endif
                        </p>
                        <p class="mt-1 text-gray-600 dark:text-gray-400">{{ $conversation->kind->topic($conversation->fulfillment?->order_id, $conversation->listing?->title) }}</p>
                        @if ($conversation->latestMessage)
                            <p class="mt-1 text-gray-500">{{ str($conversation->latestMessage->body)->limit(120) }}</p>
                        @endif
                    </div>
                    <p class="ml-auto text-gray-500">{{ $conversation->last_message_at?->format('M j, Y g:ia') }}</p>
                </a>
            </li>
        @endforeach
    </ul>
@endif
