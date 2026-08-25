<x-layouts.seller :title="$conversation->counterpartName($viewer).' — Art Store seller'">
    <x-messaging.thread :conversation="$conversation" :viewer="$viewer" index-route="seller.messages.index" store-route="seller.messages.store">
        @if ($conversation->listing)
            <section aria-labelledby="faq-heading" class="mt-6 max-w-xl">
                <h2 id="faq-heading" class="font-semibold text-gray-700 dark:text-gray-300">Publish as FAQ</h2>

                <form method="POST" action="{{ route('seller.listings.faqs.store', $conversation->listing) }}"
                      class="mt-2 rounded border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-900 p-4">
                    @csrf
                    <input type="hidden" name="source_message_id" value="{{ old('source_message_id', $faqPrefill?->sourceMessageId) }}">

                    <label for="question" class="block font-medium text-gray-700 dark:text-gray-300">Question</label>
                    <input id="question" name="question" type="text" required maxlength="500"
                           value="{{ old('question', $faqPrefill?->question) }}"
                           class="mt-1 block w-full rounded border border-gray-400 dark:border-gray-600 px-3 py-2">
                    @error('question')
                        <p class="mt-1 text-red-700 dark:text-red-400">{{ $message }}</p>
                    @enderror

                    <label for="answer" class="mt-4 block font-medium text-gray-700 dark:text-gray-300">Answer</label>
                    <textarea id="answer" name="answer" required rows="4" maxlength="2000"
                              class="mt-1 block w-full rounded border border-gray-400 dark:border-gray-600 px-3 py-2">{{ old('answer', $faqPrefill?->answer) }}</textarea>
                    @error('answer')
                        <p class="mt-1 text-red-700 dark:text-red-400">{{ $message }}</p>
                    @enderror

                    <button type="submit" class="mt-4 rounded bg-gray-900 dark:bg-gray-100 px-4 py-2 font-medium text-white dark:text-gray-900">Publish as FAQ</button>
                </form>
            </section>
        @endif
    </x-messaging.thread>
</x-layouts.seller>
