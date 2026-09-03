{{--
    The seller inbox's rows, shared by the messages index (nothing selected)
    and show (`selected` marks the open thread) screens, so both render the
    exact same list — the list-detail pane on the left and, below `lg`, the
    whole screen.
--}}
@props(['conversations', 'viewer', 'showRoute', 'domain', 'selected' => null, 'total' => null, 'indexRoute' => null])

@if ($conversations->isEmpty())
    @php
        // Context-aware in place of a bare "Nothing yet.": what the current
        // domain tab is empty of, else the bare fallback.
        $domainNames = ['buyers' => 'buyer', 'support' => 'support'];
        $emptyMessage = $domain !== 'all' ? 'No '.$domainNames[$domain].' conversations.' : 'No conversations yet.';
    @endphp
    <p class="p-6 text-sm text-gray-500 dark:text-gray-500">
        {{ $emptyMessage }}
        @if ($domain !== 'all' && $indexRoute !== null)
            <a href="{{ route($indexRoute, ['domain' => 'all']) }}" class="underline hover:text-gray-700 dark:hover:text-gray-300">Show all</a>
        @endif
    </p>
@else
    @php
        // A row's own link carries the pane's current domain, so the show
        // route it points at can render the same pane back — the window the
        // current domain tab left it in, rather than resetting to the show
        // route's default.
        $rowRouteParams = fn ($conversation) => ['conversation' => $conversation, 'domain' => $domain];
    @endphp
    <ul role="list" class="divide-y divide-gray-100 dark:divide-white/5">
        @foreach ($conversations as $conversation)
            @php
                $isSelected = $selected !== null && $selected->id === $conversation->id;
                $isUnread = $conversation->unread_count > 0;
                $isResolved = $conversation->status() === \App\Domain\Messaging\ConversationStatus::Resolved;
                [$tagLabel, $tagClasses] = match ($conversation->kind) {
                    \App\Domain\Messaging\ConversationKind::ListingQuestion => ['Question', 'bg-indigo-50 text-indigo-700 dark:bg-indigo-500/10 dark:text-indigo-300'],
                    \App\Domain\Messaging\ConversationKind::Fulfillment => ['Order', 'bg-green-50 text-green-700 dark:bg-green-500/10 dark:text-green-400'],
                    \App\Domain\Messaging\ConversationKind::AdminSeller, \App\Domain\Messaging\ConversationKind::AdminCustomer => ['Support', 'bg-gray-100 text-gray-600 dark:bg-white/10 dark:text-gray-400'],
                };
                // A row's own title: the conversation's (a listing question's
                // derived summary, or a support thread's typed subject),
                // falling back to the order for a fulfillment thread, which
                // carries none.
                $topic = $conversation->title ?? $conversation->kind->topic($conversation->fulfillment?->order_id, null);
                $at = $conversation->last_message_at;
                $relativeTime = $at === null ? '' : \App\Support\RelativeTime::short($at, now());

                // The preview line's own prefix: the listing a question is
                // about (the row's title above is the question, not the
                // piece), or who sent the latest message otherwise.
                $latest = $conversation->latestMessage;
                $previewPrefix = null;
                $previewSeparator = ': ';
                if ($latest !== null && $conversation->kind === \App\Domain\Messaging\ConversationKind::ListingQuestion && $conversation->listing !== null) {
                    $previewPrefix = $conversation->listing->title;
                    $previewSeparator = ' &middot; ';
                } elseif ($latest !== null) {
                    $isLatestMine = $latest->sender_type === $viewer->value && $latest->sender_id === $conversation->participantIdFor($viewer);
                    $previewPrefix = $isLatestMine ? 'You' : $conversation->counterpartName($viewer);
                }
            @endphp
            <li>
                <x-pane-row
                    accent="indigo"
                    :selected="$isSelected"
                    href="{{ route($showRoute, $rowRouteParams($conversation)) }}"
                    :aria-current="$isSelected ? 'true' : null"
                >
                    <x-slot:title>
                        <p class="flex items-center gap-x-1.5 text-sm/6">
                            @if ($isUnread)
                                <span class="size-1.5 shrink-0 rounded-full bg-indigo-500" aria-hidden="true"></span>
                                <span class="sr-only">{{ $conversation->unread_count }} unread</span>
                            @endif
                            <span class="min-w-0 flex-1 truncate {{ $isUnread ? 'font-semibold text-gray-900 dark:text-white' : 'font-medium text-gray-700 dark:text-gray-300' }}">{{ $conversation->counterpartName($viewer) }}</span>
                            @if ($isResolved)
                                <svg viewBox="0 0 20 20" fill="currentColor" aria-hidden="true" class="size-3.5 shrink-0 text-gray-400 dark:text-gray-500">
                                    <path fill-rule="evenodd" d="M16.704 4.153a.75.75 0 0 1 .143 1.052l-8 10.5a.75.75 0 0 1-1.127.075l-4.5-4.5a.75.75 0 0 1 1.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 0 1 1.05-.143Z" clip-rule="evenodd" />
                                </svg>
                                <span class="sr-only">Resolved</span>
                            @endif
                            <span class="shrink-0 text-xs/5 text-gray-500 dark:text-gray-400">{{ $relativeTime }}</span>
                        </p>
                    </x-slot:title>
                    <x-slot:supporting>
                        <div class="mt-1 flex items-center gap-x-2">
                            <span class="inline-flex shrink-0 rounded px-1.5 py-0.5 text-[11px] font-medium tracking-wide uppercase {{ $tagClasses }}">{{ $tagLabel }}</span>
                            <span class="min-w-0 flex-1 truncate text-xs/5 text-gray-900 dark:text-gray-100">{{ $topic }}</span>
                        </div>
                    </x-slot:supporting>
                    @if ($latest)
                        <x-slot:preview>
                            <p class="mt-1 truncate text-xs/5 text-gray-500 dark:text-gray-400">
                                @if ($previewPrefix){{ $previewPrefix }}{!! $previewSeparator !!}@endif{{ str($latest->body)->limit(80) }}
                            </p>
                        </x-slot:preview>
                    @endif
                </x-pane-row>
            </li>
        @endforeach
    </ul>

    @if ($total !== null && $indexRoute !== null)
        <x-seller.messaging.list-footer :shown="$conversations->count()" :total="$total" :route="route($indexRoute)" />
    @endif
@endif
