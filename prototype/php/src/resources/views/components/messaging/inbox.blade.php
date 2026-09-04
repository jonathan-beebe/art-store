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
@props(['conversations', 'viewer', 'showRoute', 'domain', 'selected' => null, 'indexRoute' => null])

@if ($conversations->isEmpty())
    @php
        // Context-aware in place of a bare "Nothing yet.": what the current
        // domain tab is empty of, else the bare fallback.
        $domainNames = ['sellers' => 'seller', 'customers' => 'customer'];
        $emptyMessage = $domain !== 'all' ? 'No '.$domainNames[$domain].' conversations.' : 'No conversations yet.';
    @endphp
    <p class="p-6 text-sm text-stone-500 dark:text-stone-500">
        {{ $emptyMessage }}
        @if ($domain !== 'all' && $indexRoute !== null)
            <a href="{{ route($indexRoute, ['domain' => 'all']) }}" class="underline hover:text-stone-700 dark:hover:text-stone-300">Show all</a>
        @endif
    </p>
@else
    @php
        // A row's own link carries the pane's current domain, so the show
        // route it points at can render the same pane back — the window the
        // current domain tab left it in, rather than resetting to `all`.
        $rowRouteParams = fn ($conversation) => ['conversation' => $conversation, 'domain' => $domain];
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
                <x-pane-row
                    accent="stone"
                    :selected="$isSelected"
                    href="{{ route($showRoute, $rowRouteParams($conversation)) }}"
                    :aria-current="$isSelected ? 'page' : null"
                >
                    <x-slot:title>
                        <p class="flex min-w-0 items-center gap-x-1.5 text-sm/6 {{ $isUnread ? 'font-semibold text-stone-900 dark:text-white' : 'font-medium text-stone-700 dark:text-stone-300' }}">
                            @if ($isUnread)
                                <span class="size-1.5 shrink-0 rounded-full bg-stone-500" aria-hidden="true"></span>
                                <span class="sr-only">{{ $conversation->unread_count }} unread</span>
                            @endif
                            <span class="min-w-0 flex-1 truncate">{{ $conversation->counterpartName($viewer) }}</span>
                            @if ($conversation->resolved_at !== null)
                                <svg viewBox="0 0 20 20" fill="currentColor" aria-hidden="true" class="size-3.5 shrink-0 text-stone-400 dark:text-stone-500">
                                    <path fill-rule="evenodd" d="M16.704 4.153a.75.75 0 0 1 .143 1.052l-8 10.5a.75.75 0 0 1-1.127.075l-4.5-4.5a.75.75 0 0 1 1.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 0 1 1.05-.143Z" clip-rule="evenodd" />
                                </svg>
                                <span class="sr-only">Resolved</span>
                            @endif
                            <span class="shrink-0 text-xs/5 text-stone-500 dark:text-stone-400">{{ $conversation->last_message_at?->diffForHumans() }}</span>
                        </p>
                    </x-slot:title>
                    <x-slot:supporting>
                        <p class="mt-1 flex min-w-0 items-center gap-x-2">
                            <x-messaging.kind-tag :kind="$conversation->kind" />
                            <span class="truncate text-xs/5 text-stone-700 dark:text-stone-300">{{ $topic }}</span>
                        </p>
                    </x-slot:supporting>
                    @if ($previewParts !== [])
                        <x-slot:preview>
                            <p class="mt-1 truncate text-xs/5 text-stone-500 dark:text-stone-400">{{ implode(' · ', $previewParts) }}</p>
                        </x-slot:preview>
                    @endif
                </x-pane-row>
            </li>
        @endforeach
    </ul>
@endif
