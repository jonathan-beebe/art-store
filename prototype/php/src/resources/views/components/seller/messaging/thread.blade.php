{{--
    The seller's half of the messaging screen's detail pane: header, the
    two-sided transcript in order, the composer, then `$slot` for anything a
    specific thread kind adds below it (the listing-question FAQ
    disclosure). Below `lg`, where the list-detail scaffold shows this pane
    alone, the back link is the only way to the inbox, so it stays out of
    the `lg:hidden` list pane.
--}}
@props(['conversation', 'viewer', 'indexRoute', 'storeRoute', 'domain', 'context', 'replyTo' => null, 'faqPrefill' => null])

@php
    $isResolved = $conversation->status() === \App\Domain\Messaging\ConversationStatus::Resolved;
    $heading = $conversation->title ?? $conversation->kind->topic($conversation->fulfillment?->order_id, null);
    $orderRoute = $conversation->fulfillment !== null ? route('seller.orders.show', $conversation->fulfillment) : null;
    $listingRoute = $conversation->listing !== null ? route('seller.listings.show', $conversation->listing) : null;
    $viewerSelf = fn ($threadMessage): bool => $threadMessage->sender_type === $viewer->value && $threadMessage->sender_id === $conversation->participantIdFor($viewer);
    $previousDay = null;
    // Every action on this page — reply, resolve, reopen — returns to this
    // same thread; carrying the pane's current domain onward is what keeps
    // the pane from snapping back to the index route's default.
    $paneRouteParams = ['conversation' => $conversation, 'domain' => $domain];
@endphp

{{--
    The transcript and the composer, with the context rail beside them at
    `2xl` and under them below it.
--}}
<div data-thread class="flex flex-col 2xl:flex-row 2xl:items-start">
    <div class="min-w-0 flex-1 px-6 py-4">
    <x-seller.back-link :route="route($indexRoute)" label="Messages" />

    <div data-thread-header class="flex flex-wrap items-start justify-between gap-4">
        <div class="min-w-0 flex-1">
            <div class="flex items-center gap-2">
                <h1 class="min-w-0 truncate text-base font-semibold text-gray-900 dark:text-white">
                    @if ($conversation->kind === \App\Domain\Messaging\ConversationKind::Fulfillment && $orderRoute)
                        <a href="{{ $orderRoute }}" class="rounded hover:underline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600">{{ $heading }}</a>
                    @else
                        {{ $heading }}
                    @endif
                </h1>
                <x-seller.status-badge :tint="$isResolved ? 'gray' : 'green'">
                    <span class="mr-1 inline-block size-1.5 rounded-full {{ $isResolved ? 'bg-gray-400' : 'bg-green-500' }}" aria-hidden="true"></span>{{ $isResolved ? 'Resolved' : 'Open' }}
                </x-seller.status-badge>
            </div>
            <p class="mt-0.5 text-sm text-gray-500 dark:text-gray-400">
                {{ $conversation->counterpartName($viewer) }}
                @if ($conversation->kind === \App\Domain\Messaging\ConversationKind::ListingQuestion && $listingRoute)
                    &middot; about <a href="{{ $listingRoute }}" class="rounded underline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600">{{ $conversation->listing->title }}</a>
                @elseif ($conversation->kind === \App\Domain\Messaging\ConversationKind::AdminSeller && $orderRoute)
                    &middot; about <a href="{{ $orderRoute }}" class="rounded underline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600">Order {{ $conversation->fulfillment->order_id }}</a>
                @endif
            </p>
        </div>

        <div data-thread-actions class="flex items-start gap-2">
            @if ($conversation->listing)
                <details class="relative">
                    <summary class="inline-flex cursor-pointer list-none items-center rounded-md bg-white px-3 py-2 text-sm font-semibold text-gray-900 shadow-xs inset-ring inset-ring-gray-300 hover:bg-gray-50 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600 marker:content-none dark:bg-white/10 dark:text-white dark:shadow-none dark:inset-ring-white/10 dark:hover:bg-white/20 [&::-webkit-details-marker]:hidden">Publish as FAQ</summary>
                    <div class="absolute right-0 z-10 mt-2 w-96 rounded-md border border-gray-200 bg-white p-4 shadow-lg dark:border-white/10 dark:bg-gray-900">
                        <form method="POST" action="{{ route('seller.listings.faqs.store', $conversation->listing) }}" class="space-y-4">
                            @csrf
                            <input type="hidden" name="source_message_id" value="{{ old('source_message_id', $faqPrefill?->sourceMessageId) }}">
                            {{-- Publishing resolves the thread (docs/messaging.md §
                                 "Open and resolved") — carrying it and the pane's
                                 own selection is what returns the seller to it,
                                 now marked resolved, rather than the listing's FAQ
                                 page. --}}
                            <input type="hidden" name="conversation_id" value="{{ old('conversation_id', $conversation->id) }}">
                            <input type="hidden" name="domain" value="{{ old('domain', $domain) }}">

                            <div>
                                <label for="question" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Question</label>
                                <input
                                    id="question" name="question" type="text" required maxlength="{{ \App\Domain\Messaging\FaqDraft::QUESTION_MAX_LENGTH }}"
                                    value="{{ old('question', $faqPrefill?->question) }}"
                                    class="mt-1 block w-full rounded-md bg-white px-3 py-1.5 text-sm text-gray-900 outline-1 -outline-offset-1 outline-gray-300 focus:outline-2 focus:-outline-offset-2 focus:outline-indigo-600 dark:bg-white/5 dark:text-white dark:outline-white/10"
                                >
                                @error('question')
                                    <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                                @enderror
                            </div>

                            <div>
                                <label for="answer" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Answer</label>
                                <textarea
                                    id="answer" name="answer" required rows="4" maxlength="{{ \App\Domain\Messaging\FaqDraft::ANSWER_MAX_LENGTH }}"
                                    class="mt-1 block w-full rounded-md bg-white px-3 py-1.5 text-sm text-gray-900 outline-1 -outline-offset-1 outline-gray-300 focus:outline-2 focus:-outline-offset-2 focus:outline-indigo-600 dark:bg-white/5 dark:text-white dark:outline-white/10"
                                >{{ old('answer', $faqPrefill?->answer) }}</textarea>
                                @error('answer')
                                    <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                                @enderror
                            </div>

                            <button type="submit" class="rounded-md bg-gray-900 px-3 py-2 text-sm font-semibold text-white hover:bg-gray-700 dark:bg-white dark:text-gray-900 dark:hover:bg-gray-200">Publish as FAQ</button>
                        </form>
                    </div>
                </details>
            @endif

            @can('resolve', $conversation)
                <form method="POST" action="{{ route('seller.messages.resolve', $paneRouteParams) }}">
                    @csrf
                    <button type="submit" class="inline-flex items-center gap-1.5 rounded-md bg-white px-3 py-2 text-sm font-semibold text-gray-900 shadow-xs inset-ring inset-ring-gray-300 hover:bg-gray-50 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600 dark:bg-white/10 dark:text-white dark:shadow-none dark:inset-ring-white/10 dark:hover:bg-white/20">
                        <svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true" class="size-4"><path d="m5 10 3.5 3.5L15 7" stroke-linecap="round" stroke-linejoin="round" /></svg>
                        Mark resolved
                    </button>
                </form>
            @endcan

            @can('reopen', $conversation)
                <form method="POST" action="{{ route('seller.messages.reopen', $paneRouteParams) }}">
                    @csrf
                    <button type="submit" class="inline-flex items-center rounded-md bg-white px-3 py-2 text-sm font-semibold text-gray-900 shadow-xs inset-ring inset-ring-gray-300 hover:bg-gray-50 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600 dark:bg-white/10 dark:text-white dark:shadow-none dark:inset-ring-white/10 dark:hover:bg-white/20">Reopen</button>
                </form>
            @endcan
        </div>
    </div>

    <ol class="mt-6 space-y-5">
        @foreach ($conversation->messages as $threadMessage)
            @php
                $isMine = $viewerSelf($threadMessage);
                $day = $threadMessage->sent_at->timezone(config('app.timezone'))->toDateString();
                $showDaySeparator = $day !== $previousDay;
                $previousDay = $day;
                $isReplyTarget = $replyTo !== null && $replyTo->id === $threadMessage->id;
                $initials = collect(preg_split('/\s+/', trim($threadMessage->senderName())))
                    ->filter()
                    ->take(2)
                    ->map(fn (string $word): string => mb_strtoupper(mb_substr($word, 0, 1)))
                    ->implode('');
            @endphp

            @if ($showDaySeparator)
                <li aria-hidden="true" class="flex items-center gap-3 text-xs text-gray-500 dark:text-gray-500">
                    <span class="h-px flex-1 bg-gray-200 dark:bg-white/10"></span>
                    {{ $threadMessage->sent_at->isToday() ? 'Today' : ($threadMessage->sent_at->isYesterday() ? 'Yesterday' : $threadMessage->sent_at->format('M j')) }}
                    <span class="h-px flex-1 bg-gray-200 dark:bg-white/10"></span>
                </li>
            @endif

            <li id="{{ $threadMessage->id }}" class="flex {{ $isMine ? 'justify-end' : 'justify-start' }}">
                <div class="flex max-w-[90%] items-start gap-3 sm:max-w-[78%] {{ $isMine ? 'flex-row-reverse' : '' }}">
                    <span
                        aria-hidden="true"
                        class="flex size-8 shrink-0 items-center justify-center rounded-full text-xs font-semibold {{ $isMine ? 'bg-gray-900 text-white dark:bg-white dark:text-gray-900' : 'bg-indigo-50 text-indigo-700 dark:bg-indigo-500/10 dark:text-indigo-300' }}"
                    >{{ $initials }}</span>

                    <div class="min-w-0 {{ $isMine ? 'rounded-tr-sm rounded-2xl bg-indigo-50 px-3.5 py-2.5 dark:bg-indigo-500/10' : '' }}">
                        <div class="flex items-baseline gap-x-2">
                            <p class="text-sm font-semibold text-gray-900 dark:text-white">{{ $isMine ? 'You' : $threadMessage->senderName() }}</p>
                            <p class="text-xs text-gray-500 dark:text-gray-400">{{ $threadMessage->sent_at->format('g:ia') }}</p>
                            @if ($isReplyTarget)
                                <span class="ml-auto shrink-0 text-xs font-medium text-indigo-600 dark:text-indigo-400">Replying</span>
                            @else
                                <a href="{{ route('seller.messages.show', [...$paneRouteParams, 'reply_to' => $threadMessage->id]) }}" class="ml-auto shrink-0 rounded text-xs text-gray-500 hover:text-gray-700 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600 dark:text-gray-400 dark:hover:text-gray-200">Reply</a>
                            @endif
                        </div>

                        @if ($threadMessage->replyTo)
                            <a href="#{{ $threadMessage->replyTo->id }}" class="mt-1.5 flex gap-2 rounded border-l-2 border-gray-300 bg-gray-50 px-2.5 py-1.5 text-xs text-gray-600 hover:bg-gray-100 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600 dark:border-white/20 dark:bg-white/5 dark:text-gray-400 dark:hover:bg-white/10">
                                <svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true" class="mt-0.5 size-3.5 shrink-0"><path d="M8 5 4 9l4 4M4 9h8a4 4 0 0 1 4 4v2" stroke-linecap="round" stroke-linejoin="round" /></svg>
                                <span><strong class="font-semibold text-gray-700 dark:text-gray-300">{{ $viewerSelf($threadMessage->replyTo) ? 'You' : $threadMessage->replyTo->senderName() }}</strong> &middot; {{ str($threadMessage->replyTo->body)->limit(60) }}</span>
                            </a>
                        @endif

                        <p class="mt-1 text-left text-sm/6 whitespace-pre-line text-gray-700 dark:text-gray-300">{{ $threadMessage->body }}</p>
                    </div>
                </div>
            </li>
        @endforeach
    </ol>

    @can('post', $conversation)
        <x-seller.messaging.composer
            :action="route($storeRoute, $paneRouteParams)"
            :reply-to="$replyTo"
            :reply-to-name="$replyTo !== null ? ($viewerSelf($replyTo) ? 'You' : $replyTo->senderName()) : null"
            :cancel-url="route('seller.messages.show', $paneRouteParams)"
        />
    @endcan

    {{ $slot }}
    </div>

    <aside data-thread-rail aria-label="About this conversation" class="w-full shrink-0 border-t border-gray-200 px-6 py-6 2xl:w-80 2xl:border-t-0 2xl:border-l dark:border-white/10">
        <x-seller.context-rail :context="$context" />
    </aside>
</div>
