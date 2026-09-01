@props(['conversation', 'viewer', 'indexRoute', 'storeRoute'])

<x-admin.back-link :route="route($indexRoute)" label="Messages" />

<div class="flex flex-wrap items-center gap-4">
    <div>
        <h1 class="text-xl font-semibold">{{ $conversation->counterpartName($viewer) }}</h1>
        <p class="text-stone-600 dark:text-stone-400">{{ $conversation->kind->topic($conversation->fulfillment?->order_id, $conversation->listing?->title) }}</p>
    </div>
    <a href="{{ route($indexRoute) }}" class="ml-auto hidden text-stone-700 dark:text-stone-300 underline sm:inline">All messages</a>
</div>

{{-- Flat rows, no per-message card — the seller thread's transcript shape:
     a sender/timestamp header line above each message's body. --}}
<ol class="mt-6 space-y-6">
    @foreach ($conversation->messages as $threadMessage)
        <li>
            <div class="flex items-baseline gap-x-2">
                <p class="text-sm font-semibold text-stone-900 dark:text-white">{{ $threadMessage->senderName() }}</p>
                <p class="text-xs text-stone-500 dark:text-stone-400">{{ $threadMessage->sent_at->format('M j, Y g:ia') }}</p>
            </div>
            <p class="mt-1 whitespace-pre-line text-sm/6 text-stone-700 dark:text-stone-300">{{ $threadMessage->body }}</p>
        </li>
    @endforeach
</ol>

@can('post', $conversation)
    <x-messaging.body-form :action="route($storeRoute, $conversation)" label="Reply" />
@endcan

{{ $slot }}
