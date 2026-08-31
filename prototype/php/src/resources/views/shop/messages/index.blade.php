<x-layouts.shop title="Messages — Art Store">
    <h1 class="font-display text-4xl leading-tight text-ink">Messages</h1>

    @if ($conversations->isEmpty())
        <p class="mt-6 text-lg text-ink-muted">Nothing yet.</p>
    @else
        <ul class="mt-6 max-w-2xl divide-y divide-line border-y border-line">
            @foreach ($conversations as $conversation)
                <li>
                    <a href="{{ route('shop.messages.show', $conversation) }}" class="flex flex-wrap items-start gap-4 py-5 hover:text-accent">
                        <div>
                            <p class="text-lg font-medium text-ink">
                                {{ $conversation->counterpartName($viewer) }}
                                @if ($conversation->unread_count > 0)
                                    <span class="ml-1 rounded-full bg-accent px-2 py-0.5 text-xs font-medium text-on-accent">{{ $conversation->unread_count }} unread</span>
                                @endif
                            </p>
                            <p class="mt-1 text-ink-muted">{{ $conversation->kind->topic($conversation->fulfillment?->order_id, $conversation->listing?->title) }}</p>
                            @if ($conversation->latestMessage)
                                <p class="mt-1 text-ink-faint">{{ str($conversation->latestMessage->body)->limit(120) }}</p>
                            @endif
                        </div>
                        <p class="ml-auto text-ink-faint">{{ $conversation->last_message_at?->format('M j, Y g:ia') }}</p>
                    </a>
                </li>
            @endforeach
        </ul>

        <div class="mt-8 max-w-2xl">{{ $conversations->links() }}</div>
    @endif
</x-layouts.shop>
