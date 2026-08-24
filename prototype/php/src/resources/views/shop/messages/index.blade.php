<x-layouts.shop title="Messages — Art Store">
    <h1 class="text-4xl font-semibold tracking-tight">Messages</h1>

    @if ($conversations->isEmpty())
        <p class="mt-6 text-lg text-neutral-600">Nothing yet.</p>
    @else
        <ul class="mt-6 max-w-2xl divide-y divide-neutral-100 border-y border-neutral-100">
            @foreach ($conversations as $conversation)
                <li>
                    <a href="{{ route('shop.messages.show', $conversation) }}" class="flex flex-wrap items-start gap-4 py-5 hover:text-neutral-600">
                        <div>
                            <p class="text-lg font-medium">
                                {{ $conversation->counterpartName($viewer) }}
                                @if ($conversation->unread_count > 0)
                                    <span class="ml-1 rounded-full bg-neutral-900 px-2 py-0.5 text-xs font-medium text-white">{{ $conversation->unread_count }} unread</span>
                                @endif
                            </p>
                            <p class="mt-1 text-neutral-600">{{ $conversation->kind->topic($conversation->fulfillment?->order_id, $conversation->listing?->title) }}</p>
                            @if ($conversation->latestMessage)
                                <p class="mt-1 text-neutral-500">{{ str($conversation->latestMessage->body)->limit(120) }}</p>
                            @endif
                        </div>
                        <p class="ml-auto text-neutral-500">{{ $conversation->last_message_at?->format('M j, Y g:ia') }}</p>
                    </a>
                </li>
            @endforeach
        </ul>
    @endif
</x-layouts.shop>
