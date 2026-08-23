<x-layouts.seller :title="$conversation->counterpartName($viewer).' — Art Store seller'">
    <div class="flex flex-wrap items-center gap-4">
        <div>
            <h1 class="text-xl font-semibold">{{ $conversation->counterpartName($viewer) }}</h1>
            <p class="text-gray-600">{{ $conversation->kind->topic($conversation->fulfillment?->order_id, $conversation->listing?->title) }}</p>
        </div>
        <a href="{{ route('seller.messages.index') }}" class="ml-auto text-gray-700 underline">All messages</a>
    </div>

    <ol class="mt-6 space-y-3">
        @foreach ($conversation->messages as $threadMessage)
            <li class="rounded border border-gray-300 bg-white p-4">
                <p class="font-medium">
                    {{ $threadMessage->senderName() }}
                    <span class="ml-2 font-normal text-gray-500">{{ $threadMessage->sent_at->format('M j, Y g:ia') }}</span>
                </p>
                <p class="mt-1 whitespace-pre-line text-gray-800">{{ $threadMessage->body }}</p>
            </li>
        @endforeach
    </ol>

    @can('post', $conversation)
        <form method="POST" action="{{ route('seller.messages.store', $conversation) }}" class="mt-6 max-w-xl rounded border border-gray-300 bg-white p-4">
            @csrf

            <label for="body" class="block font-medium text-gray-700">Reply</label>
            <textarea id="body" name="body" required rows="4" maxlength="2000"
                      class="mt-1 block w-full rounded border border-gray-400 px-3 py-2">{{ old('body') }}</textarea>
            @error('body')
                <p class="mt-1 text-red-700">{{ $message }}</p>
            @enderror

            <button type="submit" class="mt-4 rounded bg-gray-900 px-4 py-2 font-medium text-white">Send</button>
        </form>
    @endcan

    @if ($conversation->listing)
        <section aria-labelledby="faq-heading" class="mt-6 max-w-xl">
            <h2 id="faq-heading" class="font-semibold text-gray-700">Publish as FAQ</h2>

            <form method="POST" action="{{ route('seller.listings.faqs.store', $conversation->listing) }}"
                  class="mt-2 rounded border border-gray-300 bg-white p-4">
                @csrf
                <input type="hidden" name="source_message_id" value="{{ old('source_message_id', $faqPrefill?->sourceMessageId) }}">

                <label for="question" class="block font-medium text-gray-700">Question</label>
                <input id="question" name="question" type="text" required maxlength="500"
                       value="{{ old('question', $faqPrefill?->question) }}"
                       class="mt-1 block w-full rounded border border-gray-400 px-3 py-2">
                @error('question')
                    <p class="mt-1 text-red-700">{{ $message }}</p>
                @enderror

                <label for="answer" class="mt-4 block font-medium text-gray-700">Answer</label>
                <textarea id="answer" name="answer" required rows="4" maxlength="2000"
                          class="mt-1 block w-full rounded border border-gray-400 px-3 py-2">{{ old('answer', $faqPrefill?->answer) }}</textarea>
                @error('answer')
                    <p class="mt-1 text-red-700">{{ $message }}</p>
                @enderror

                <button type="submit" class="mt-4 rounded bg-gray-900 px-4 py-2 font-medium text-white">Publish as FAQ</button>
            </form>
        </section>
    @endif
</x-layouts.seller>
