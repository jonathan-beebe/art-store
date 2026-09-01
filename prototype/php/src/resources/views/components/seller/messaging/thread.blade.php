{{--
    The seller's half of the messaging screen's detail pane: header, the
    thread in order, the composer, then `$slot` for anything a specific
    thread kind adds below it (the listing-question FAQ form). Below `lg`,
    where the list-detail scaffold shows this pane alone, the back link is
    the only way to the inbox, so it stays out of the `lg:hidden` list pane.
--}}
@props(['conversation', 'viewer', 'indexRoute', 'storeRoute'])

<div class="px-6 py-4">
    <a href="{{ route($indexRoute) }}" class="mb-4 inline-flex items-center gap-x-1 text-sm text-gray-500 hover:text-gray-700 lg:hidden dark:text-gray-400 dark:hover:text-gray-200">
        <svg viewBox="0 0 20 20" fill="currentColor" aria-hidden="true" class="size-4">
            <path fill-rule="evenodd" d="M12.79 5.23a.75.75 0 0 1 0 1.06L9.06 10l3.73 3.71a.75.75 0 1 1-1.06 1.06l-4.25-4.25a.75.75 0 0 1 0-1.06l4.25-4.25a.75.75 0 0 1 1.06 0Z" clip-rule="evenodd" />
        </svg>
        Messages
    </a>

    <h1 class="text-base font-semibold text-gray-900 dark:text-white">{{ $conversation->counterpartName($viewer) }}</h1>
    <p class="text-sm text-gray-500 dark:text-gray-400">{{ $conversation->kind->topic($conversation->fulfillment?->order_id, $conversation->listing?->title) }}</p>

    <ol class="mt-6 space-y-6">
        @foreach ($conversation->messages as $threadMessage)
            <li>
                <div class="flex items-baseline gap-x-2">
                    <p class="text-sm font-semibold text-gray-900 dark:text-white">{{ $threadMessage->senderName() }}</p>
                    <p class="text-xs text-gray-500 dark:text-gray-400">{{ $threadMessage->sent_at->format('M j, Y g:ia') }}</p>
                </div>
                <p class="mt-1 whitespace-pre-line text-sm/6 text-gray-700 dark:text-gray-300">{{ $threadMessage->body }}</p>
            </li>
        @endforeach
    </ol>

    @can('post', $conversation)
        <x-seller.messaging.composer :action="route($storeRoute, $conversation)" />
    @endcan

    {{ $slot }}
</div>
