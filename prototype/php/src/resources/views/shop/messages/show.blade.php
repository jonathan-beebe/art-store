<x-layouts.shop :title="$conversation->counterpartName($viewer).' — Art Store'">
    <div class="flex flex-wrap items-baseline gap-4">
        <div>
            <h1 class="font-display text-4xl leading-tight text-ink">{{ $conversation->counterpartName($viewer) }}</h1>
            <p class="mt-2 text-lg text-ink-muted">{{ $conversation->kind->topic($conversation->fulfillment?->order_id, $conversation->listing?->title) }}</p>
        </div>
        <a href="{{ route('shop.messages.index') }}" class="ml-auto text-ink-muted underline hover:text-accent">All messages</a>
    </div>

    <ol class="mt-10 max-w-2xl space-y-4">
        @foreach ($conversation->messages as $threadMessage)
            <li class="rounded-card border border-line p-5">
                <p class="font-medium text-ink">
                    {{ $threadMessage->senderName() }}
                    <span class="ml-2 font-normal text-ink-faint">{{ $threadMessage->sent_at->format('M j, Y g:ia') }}</span>
                </p>
                <p class="mt-2 whitespace-pre-line text-ink-muted">{{ $threadMessage->body }}</p>
            </li>
        @endforeach
    </ol>

    @visitorCan('post', $conversation)
        <form method="POST" action="{{ route('shop.messages.store', $conversation) }}" class="mt-8 max-w-2xl">
            @csrf

            <x-ui.label for="body">Reply</x-ui.label>
            <x-ui.textarea id="body" name="body" required rows="4" maxlength="2000" class="mt-1">{{ old('body') }}</x-ui.textarea>
            @error('body')
                <p class="mt-1 text-danger">{{ $message }}</p>
            @enderror

            <x-ui.button variant="primary" class="mt-4">Send</x-ui.button>
        </form>
    @endvisitorCan
</x-layouts.shop>
