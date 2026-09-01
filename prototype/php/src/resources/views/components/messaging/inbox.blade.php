{{--
    The admin inbox's rows, shared by the messages index (nothing selected)
    and show (`selected` marks the open thread) screens, so both render the
    exact same list — the `lg`-and-up list pane (DSGN-006's `cells` slot) and,
    below `lg`, the whole screen. Admin-exclusive: the seller portal keeps
    its own `x-seller.messaging.inbox` rather than sharing this one, so the
    stone tint below is safe to apply without touching another portal.

    Bare rows, no border/background of its own — the `cells` pane already
    supplies both (DSGN-006's list-pane chrome), and the below-`lg` caller
    supplies its own card wrapper the same way every other admin index's
    below-`lg` list does.
--}}
@props(['conversations', 'viewer', 'showRoute', 'selected' => null])

@if ($conversations->isEmpty())
    <p class="p-6 text-sm text-stone-500 dark:text-stone-500">Nothing yet.</p>
@else
    <ul role="list" class="divide-y divide-stone-100 dark:divide-white/5">
        @foreach ($conversations as $conversation)
            @php
                $isSelected = $selected !== null && $selected->id === $conversation->id;
                $isUnread = $conversation->unread_count > 0;
            @endphp
            <li>
                <a
                    href="{{ route($showRoute, $conversation) }}"
                    @if ($isSelected) aria-current="true" @endif
                    class="block px-6 py-4 hover:bg-stone-50 dark:hover:bg-white/5 {{ $isSelected ? 'bg-stone-50 ring-2 ring-inset ring-stone-500 dark:bg-white/5' : '' }}"
                >
                    <p class="flex min-w-0 items-center gap-x-1.5 truncate text-sm {{ $isUnread ? 'font-semibold text-stone-900 dark:text-white' : 'font-medium text-stone-700 dark:text-stone-300' }}">
                        @if ($isUnread)
                            <span class="size-1.5 shrink-0 rounded-full bg-stone-500" aria-hidden="true"></span>
                            <span class="sr-only">{{ $conversation->unread_count }} unread</span>
                        @endif
                        <span class="truncate">{{ $conversation->counterpartName($viewer) }} &middot; {{ $conversation->kind->topic($conversation->fulfillment?->order_id, $conversation->listing?->title) }}</span>
                    </p>

                    <p class="mt-1 truncate text-xs text-stone-500 dark:text-stone-400">
                        @if ($conversation->latestMessage){{ str($conversation->latestMessage->body)->limit(80) }} &middot; @endif{{ $conversation->last_message_at?->format('M j, Y g:ia') }}
                    </p>
                </a>
            </li>
        @endforeach
    </ul>
@endif
