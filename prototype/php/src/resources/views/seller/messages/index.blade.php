<x-layouts.seller title="Messages — Art Store seller">
    <h1 class="text-xl font-semibold">Messages</h1>

    @if ($conversations->isEmpty())
        <p class="mt-4 rounded border border-gray-300 bg-white p-4 text-gray-600">Nothing yet.</p>
    @else
        <ul class="mt-4 divide-y divide-gray-200 rounded border border-gray-300 bg-white">
            @foreach ($conversations as $conversation)
                <li>
                    <a href="{{ route('seller.messages.show', $conversation) }}" class="flex flex-wrap items-start gap-4 p-4 hover:bg-gray-50">
                        <div>
                            <p class="font-medium">
                                {{ $conversation->counterpartName($viewer) }}
                                @if ($conversation->unread_count > 0)
                                    <span class="ml-1 rounded bg-gray-900 px-2 py-0.5 text-xs font-medium text-white">{{ $conversation->unread_count }} unread</span>
                                @endif
                            </p>
                            <p class="mt-1 text-gray-600">{{ $conversation->kind->topic($conversation->fulfillment?->order_id, $conversation->listing?->title) }}</p>
                            @if ($conversation->latestMessage)
                                <p class="mt-1 text-gray-500">{{ \Illuminate\Support\Str::limit($conversation->latestMessage->body, 120) }}</p>
                            @endif
                        </div>
                        <p class="ml-auto text-gray-500">{{ $conversation->last_message_at?->format('M j, Y g:ia') }}</p>
                    </a>
                </li>
            @endforeach
        </ul>
    @endif
</x-layouts.seller>
