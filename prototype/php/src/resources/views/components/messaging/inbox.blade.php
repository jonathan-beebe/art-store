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
@props(['conversations', 'viewer', 'showRoute', 'selected' => null, 'filter' => null, 'status' => null])

@if ($conversations->isEmpty())
    <p class="p-6 text-sm text-stone-500 dark:text-stone-500">Nothing yet.</p>
@else
    @php
        // A row's own link carries the pane's current filter/status, so the
        // show route it points at can render the same pane back — the
        // window a filtered pane left it in, rather than resetting to an
        // unfiltered one.
        $rowRouteParams = fn ($conversation) => array_filter(
            ['conversation' => $conversation, 'filter' => $filter, 'status' => $status],
            fn ($value) => $value !== null,
        );
    @endphp
    <ul role="list" class="divide-y divide-stone-100 dark:divide-white/5">
        @foreach ($conversations as $conversation)
            @php
                $isSelected = $selected !== null && $selected->id === $conversation->id;
                // An oversight thread is never marked read (docs/messaging.md
                // § "Who may read, post, and resolve"), so its unread count
                // never settles — the dot is desk-kind-only, the one signal
                // that means "waiting on the desk" rather than "not yet
                // touched".
                $isUnread = $conversation->kind->isDesk() && $conversation->unread_count > 0;
                $orderId = $conversation->fulfillment?->order_id ?? $conversation->order_id;
                $topic = $conversation->kind->isDesk()
                    ? $conversation->title
                    : $conversation->kind->topic($conversation->fulfillment?->order_id, $conversation->listing?->title);

                $previewParts = [];
                if ($orderId !== null) {
                    $previewParts[] = "Order {$orderId}";
                }
                if ($conversation->latestMessage) {
                    $previewParts[] = \App\Support\ActorDisplay::nameOf($conversation->latestMessage->sender).': '.str($conversation->latestMessage->body)->limit(80);
                }
                if ($conversation->admin_id !== null) {
                    $previewParts[] = 'handled by '.\App\Support\ActorDisplay::nameOf($conversation->admin);
                }
            @endphp
            <li>
                <a
                    href="{{ route($showRoute, $rowRouteParams($conversation)) }}"
                    @if ($isSelected) aria-current="true" @endif
                    class="block px-6 py-4 hover:bg-stone-50 dark:hover:bg-white/5 {{ $isSelected ? 'bg-stone-50 ring-2 ring-inset ring-stone-500 dark:bg-white/5' : '' }}"
                >
                    <p class="flex min-w-0 items-center gap-x-1.5 text-sm {{ $isUnread ? 'font-semibold text-stone-900 dark:text-white' : 'font-medium text-stone-700 dark:text-stone-300' }}">
                        @if ($isUnread)
                            <span class="size-1.5 shrink-0 rounded-full bg-stone-500" aria-hidden="true"></span>
                            <span class="sr-only">{{ $conversation->unread_count }} unread</span>
                        @endif
                        <span class="min-w-0 flex-1 truncate">{{ $conversation->counterpartName($viewer) }}</span>
                        <span class="shrink-0 text-xs font-normal text-stone-500 dark:text-stone-400">{{ $conversation->last_message_at?->diffForHumans() }}</span>
                    </p>

                    <p class="mt-1 flex min-w-0 items-center gap-x-2">
                        <x-messaging.kind-tag :kind="$conversation->kind" />
                        <span class="truncate text-sm text-stone-700 dark:text-stone-300">{{ $topic }}</span>
                    </p>

                    @if ($previewParts !== [])
                        <p class="mt-1 truncate text-xs text-stone-500 dark:text-stone-400">{{ implode(' · ', $previewParts) }}</p>
                    @endif
                </a>
            </li>
        @endforeach
    </ul>
@endif
