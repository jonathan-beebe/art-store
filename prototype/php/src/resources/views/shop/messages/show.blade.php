<x-layouts.shop :title="$conversation->counterpartName($viewer).' — Art Store'">
    <div class="flex flex-wrap items-baseline gap-4">
        <div>
            <h1 class="text-4xl font-semibold tracking-tight">{{ $conversation->counterpartName($viewer) }}</h1>
            <p class="mt-2 text-lg text-neutral-600">{{ $conversation->kind->topic($conversation->fulfillment?->order_id, $conversation->listing?->title) }}</p>
        </div>
        <a href="{{ route('shop.messages.index') }}" class="ml-auto text-neutral-600 underline hover:text-neutral-900">All messages</a>
    </div>

    <ol class="mt-10 max-w-2xl space-y-4">
        @foreach ($conversation->messages as $threadMessage)
            <li class="rounded-2xl border border-neutral-200 p-5">
                <p class="font-medium">
                    {{ $threadMessage->senderName() }}
                    <span class="ml-2 font-normal text-neutral-500">{{ $threadMessage->sent_at->format('M j, Y g:ia') }}</span>
                </p>
                <p class="mt-2 whitespace-pre-line text-neutral-800">{{ $threadMessage->body }}</p>
            </li>
        @endforeach
    </ol>

    @visitorCan('post', $conversation)
        <form method="POST" action="{{ route('shop.messages.store', $conversation) }}" class="mt-8 max-w-2xl">
            @csrf

            <label for="body" class="block font-medium text-neutral-700">Reply</label>
            <textarea id="body" name="body" required rows="4" maxlength="2000"
                      class="mt-1 block w-full rounded-2xl border border-neutral-300 px-4 py-3 focus:border-neutral-900 focus:outline-none">{{ old('body') }}</textarea>
            @error('body')
                <p class="mt-1 text-red-700">{{ $message }}</p>
            @enderror

            <button type="submit" class="mt-4 rounded-full bg-neutral-900 px-8 py-3 text-base font-medium text-white">Send</button>
        </form>
    @endvisitorCan
</x-layouts.shop>
