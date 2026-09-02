{{--
    A thread's header, transcript, and (desk kinds only) composer —
    docs/messaging.md § "The transcript" and § "Who may read, post, and
    resolve". `@can('post', ...)` is the one branch this reads: true on a
    desk thread renders the per-message "Reply" links and the composer at
    the foot; false on an oversight thread (seller <-> customer) renders
    neither, and the notice bar above the transcript offers the two
    "Message ..." buttons instead. The same branch is why a message's own
    tint never needs an `isDesk()` check of its own: only an admin ever
    posts into a desk thread, so `$message->sender instanceof Admin` is
    already exactly the messages that belong on the right.
--}}
@props(['conversation', 'viewer', 'indexRoute', 'storeRoute', 'replyTo' => null, 'filter' => null, 'status' => null])

@php
    $isResolved = $conversation->status() === \App\Domain\Messaging\ConversationStatus::Resolved;
    $orderId = $conversation->fulfillment?->order_id ?? $conversation->order_id;
    $resolvedByName = $conversation->resolvedBy instanceof \App\Models\Seller || $conversation->resolvedBy instanceof \App\Models\Admin
        ? \App\Support\ActorDisplay::nameOf($conversation->resolvedBy)
        : null;
    // Every action on this page — reply, resolve, reopen — returns to this
    // same thread; carrying the pane's current filter/status onward is what
    // keeps the pane from snapping back to the unscoped default.
    $paneRouteParams = array_filter(
        ['conversation' => $conversation, 'filter' => $filter, 'status' => $status],
        fn ($value) => $value !== null,
    );
@endphp

<x-admin.back-link :route="route($indexRoute)" label="Messages" />

<div class="flex flex-wrap items-start gap-4">
    <div class="min-w-0 flex-1">
        <div class="flex flex-wrap items-center gap-2">
            <h1 class="text-xl font-semibold">{{ $conversation->counterpartName($viewer) }}</h1>
            <span class="inline-flex items-center gap-1.5 rounded-md px-2 py-0.5 text-xs font-medium {{ $isResolved ? 'bg-stone-100 text-stone-600 dark:bg-white/10 dark:text-stone-400' : 'bg-green-50 text-green-700 dark:bg-green-400/10 dark:text-green-400' }}">
                <span class="size-1.5 rounded-full {{ $isResolved ? 'bg-stone-400' : 'bg-green-500' }}" aria-hidden="true"></span>
                {{ $isResolved ? 'Resolved'.($resolvedByName ? ' by '.$resolvedByName : '') : 'Open' }}
            </span>
        </div>
        <p class="mt-1 flex flex-wrap items-center gap-x-1.5 text-stone-600 dark:text-stone-400">
            @if ($conversation->kind->isDesk())
                <span>{{ $conversation->title }}</span>
                @if ($conversation->seller)
                    &middot; <a href="{{ route('admin.sellers.show', $conversation->seller) }}" class="underline">{{ $conversation->seller->displayName() }}</a>
                @endif
                @if ($conversation->customer)
                    &middot; <a href="{{ route('admin.customers.show', $conversation->customer) }}" class="underline">{{ \App\Support\ActorDisplay::nameOf($conversation->customer) }}</a>
                @endif
                @if ($conversation->fulfillment)
                    &middot; <a href="{{ route('admin.fulfillments.show', $conversation->fulfillment) }}" class="underline">order {{ $conversation->fulfillment->order_id }}</a>
                @endif
                @if ($conversation->order)
                    &middot; <a href="{{ route('admin.orders.show', $conversation->order) }}" class="underline">order {{ $conversation->order->id }}</a>
                @endif
                @if ($conversation->admin_id !== null)
                    &middot; handled by <span class="text-stone-900 dark:text-stone-100">{{ \App\Support\ActorDisplay::nameOf($conversation->admin) }}</span>
                @endif
            @else
                <a href="{{ route('admin.sellers.show', $conversation->seller) }}" class="underline">{{ $conversation->seller->displayName() }}</a>
                &middot;
                <a href="{{ route('admin.customers.show', $conversation->customer) }}" class="underline">{{ \App\Support\ActorDisplay::nameOf($conversation->customer) }}</a>
                <x-messaging.kind-tag :kind="$conversation->kind" />
                <span>{{ $conversation->kind->topic($orderId, $conversation->listing?->title) }}</span>
            @endif
        </p>
    </div>

    @can('resolve', $conversation)
        <form method="POST" action="{{ route('admin.messages.resolve', $paneRouteParams) }}">
            @csrf
            <button type="submit" class="inline-flex items-center gap-1.5 rounded-md bg-white px-3 py-1.5 text-sm font-semibold text-stone-900 shadow-xs ring-1 ring-inset ring-stone-300 hover:bg-stone-50 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-stone-700 dark:bg-white/10 dark:text-white dark:ring-white/10">Mark resolved</button>
        </form>
    @endcan
    @can('reopen', $conversation)
        <form method="POST" action="{{ route('admin.messages.reopen', $paneRouteParams) }}">
            @csrf
            <button type="submit" class="inline-flex items-center gap-1.5 rounded-md bg-white px-3 py-1.5 text-sm font-semibold text-stone-900 shadow-xs ring-1 ring-inset ring-stone-300 hover:bg-stone-50 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-stone-700 dark:bg-white/10 dark:text-white dark:ring-white/10">Reopen</button>
        </form>
    @endcan

    <a href="{{ route($indexRoute) }}" class="hidden text-stone-700 dark:text-stone-300 underline sm:inline">All messages</a>
</div>

@cannot('post', $conversation)
    <div role="note" class="mt-4 flex flex-wrap items-center gap-3 rounded-md border border-stone-200 bg-stone-50 px-4 py-2.5 text-sm text-stone-600 dark:border-white/10 dark:bg-white/5 dark:text-stone-400">
        <svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true" class="size-4 shrink-0">
            <path d="M10 12.5V9m0-3h.01M18 10a8 8 0 1 1-16 0 8 8 0 0 1 16 0Z" stroke-linecap="round" stroke-linejoin="round" />
        </svg>
        <p>You're reading a conversation between a seller and a customer. To step in, open a support thread with either of them{{ $orderId ? ' — the order comes along' : '' }}.</p>
        <div class="ml-auto flex shrink-0 flex-wrap gap-2">
            @php
                $sellerMessageUrl = route('admin.sellers.show', $conversation->seller)
                    .($conversation->fulfillment_id ? '?fulfillment='.$conversation->fulfillment_id : '')
                    .'#message-seller-form';
                $customerMessageUrl = route('admin.customers.show', $conversation->customer)
                    .($orderId ? '?order='.$orderId : '')
                    .'#message-customer-form';
            @endphp
            <a href="{{ $sellerMessageUrl }}" class="rounded-md bg-white px-3 py-1.5 text-sm font-semibold text-stone-900 shadow-xs ring-1 ring-inset ring-stone-300 hover:bg-stone-50 dark:bg-white/10 dark:text-white dark:ring-white/10">Message {{ $conversation->seller->displayName() }}</a>
            <a href="{{ $customerMessageUrl }}" class="rounded-md bg-white px-3 py-1.5 text-sm font-semibold text-stone-900 shadow-xs ring-1 ring-inset ring-stone-300 hover:bg-stone-50 dark:bg-white/10 dark:text-white dark:ring-white/10">Message {{ \App\Support\ActorDisplay::nameOf($conversation->customer) }}</a>
        </div>
    </div>
@endcannot

{{-- Two columns of voice (docs/messaging.md § "The transcript"): a desk
     message sits on the right in a tinted panel, everyone else on the left
     on the plain surface — on an oversight thread nobody posts as the desk,
     so every message lands on the left with its own avatar. --}}
<ol class="mt-6 space-y-4">
    @php $previousDay = null; @endphp
    @foreach ($conversation->messages as $threadMessage)
        @php $day = $threadMessage->sent_at->toDateString(); @endphp
        @if ($day !== $previousDay)
            <li aria-hidden="true" class="flex items-center gap-3 text-xs text-stone-500 dark:text-stone-500">
                <span class="h-px flex-1 bg-stone-200 dark:bg-white/10"></span>
                <span>{{ $threadMessage->sent_at->format('M j') }}</span>
                <span class="h-px flex-1 bg-stone-200 dark:bg-white/10"></span>
            </li>
            @php $previousDay = $day; @endphp
        @endif

        @php $mine = $threadMessage->sender instanceof \App\Models\Admin; @endphp
        <li id="msg_{{ $threadMessage->id }}" class="flex max-w-[90%] gap-3 sm:max-w-[78%] {{ $mine ? 'ml-auto flex-row-reverse' : '' }}">
            <x-messaging.avatar :actor="$threadMessage->sender" />
            <div class="min-w-0 {{ $mine ? 'rounded-xl rounded-tr-sm bg-stone-100 px-3.5 py-2.5 ring-1 ring-stone-300 dark:bg-white/5 dark:ring-white/10' : '' }}">
                <div class="flex flex-wrap items-baseline gap-x-2">
                    <span class="text-sm font-semibold text-stone-900 dark:text-white">{{ $threadMessage->senderName() }}</span>
                    <span class="text-xs text-stone-500 dark:text-stone-400">{{ $threadMessage->sent_at->format('g:ia') }}</span>
                    @can('post', $conversation)
                        <a href="{{ route('admin.messages.show', [...$paneRouteParams, 'reply_to' => $threadMessage->id]) }}" class="ml-auto text-xs font-medium text-stone-500 underline hover:text-stone-800 dark:text-stone-400 dark:hover:text-stone-200">Reply</a>
                    @endcan
                </div>
                @if ($threadMessage->replyTo)
                    <a href="#msg_{{ $threadMessage->replyTo->id }}" class="mt-1 block truncate border-l-2 border-stone-300 pl-2 text-xs text-stone-500 dark:border-white/10 dark:text-stone-400">{{ $threadMessage->replyTo->senderName() }}: {{ str($threadMessage->replyTo->body)->limit(80) }}</a>
                @endif
                <p class="mt-1 whitespace-pre-line text-sm/6 text-stone-700 dark:text-stone-300">{{ $threadMessage->body }}</p>
            </div>
        </li>
    @endforeach
</ol>

@can('post', $conversation)
    <x-messaging.body-form :conversation="$conversation" :action="route($storeRoute, $paneRouteParams)" :reply-to="$replyTo" :filter="$filter" :status="$status" />
@endcan

{{ $slot }}
