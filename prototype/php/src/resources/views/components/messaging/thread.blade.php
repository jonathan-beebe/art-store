@props(['conversation', 'viewer', 'indexRoute', 'storeRoute'])

<div class="flex flex-wrap items-center gap-4">
    <div>
        <h1 class="text-xl font-semibold">{{ $conversation->counterpartName($viewer) }}</h1>
        <p class="text-gray-600 dark:text-gray-400">{{ $conversation->kind->topic($conversation->fulfillment?->order_id, $conversation->listing?->title) }}</p>
    </div>
    <a href="{{ route($indexRoute) }}" class="ml-auto text-gray-700 dark:text-gray-300 underline">All messages</a>
</div>

<ol class="mt-6 space-y-3">
    @foreach ($conversation->messages as $threadMessage)
        <li class="rounded border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-900 p-4">
            <p class="font-medium">
                {{ $threadMessage->senderName() }}
                <span class="ml-2 font-normal text-gray-500">{{ $threadMessage->sent_at->format('M j, Y g:ia') }}</span>
            </p>
            <p class="mt-1 whitespace-pre-line text-gray-800 dark:text-gray-200">{{ $threadMessage->body }}</p>
        </li>
    @endforeach
</ol>

@can('post', $conversation)
    <x-messaging.body-form :action="route($storeRoute, $conversation)" label="Reply" />
@endcan

{{ $slot }}
