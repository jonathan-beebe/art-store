<x-layouts.seller :title="$conversation->counterpartName($viewer).' — Art Store seller'">
    <div class="-mx-4 -my-6 h-[calc(100dvh-4rem)] sm:-mx-6 lg:-mx-8">
        <x-seller.list-detail mobile="detail">
            <x-slot:listHeader>
                <div class="flex items-baseline justify-between gap-x-2">
                    <h2 class="text-base font-semibold text-gray-900 dark:text-white">Messages</h2>
                    <span class="text-xs text-gray-500 dark:text-gray-400">{{ $cellConversationsTotal }}</span>
                </div>
            </x-slot:listHeader>

            <x-slot:list>
                <x-seller.messaging.inbox
                    :conversations="$cellConversations"
                    :viewer="$viewer"
                    show-route="seller.messages.show"
                    :selected="$conversation"
                    :total="$cellConversationsTotal"
                    index-route="seller.messages.index"
                />
            </x-slot:list>

            <x-seller.messaging.thread :conversation="$conversation" :viewer="$viewer" index-route="seller.messages.index" store-route="seller.messages.store">
                @if ($conversation->listing)
                    <section aria-labelledby="faq-heading" class="mt-8 max-w-xl border-t border-gray-200 pt-6 dark:border-white/10">
                        <h2 id="faq-heading" class="text-sm font-semibold text-gray-900 dark:text-white">Publish as FAQ</h2>

                        <form method="POST" action="{{ route('seller.listings.faqs.store', $conversation->listing) }}" class="mt-3 space-y-4">
                            @csrf
                            <input type="hidden" name="source_message_id" value="{{ old('source_message_id', $faqPrefill?->sourceMessageId) }}">

                            <div>
                                <label for="question" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Question</label>
                                <input
                                    id="question" name="question" type="text" required maxlength="500"
                                    value="{{ old('question', $faqPrefill?->question) }}"
                                    class="mt-1 block w-full rounded-md bg-white px-3 py-1.5 text-sm text-gray-900 outline-1 -outline-offset-1 outline-gray-300 focus:outline-2 focus:-outline-offset-2 focus:outline-indigo-600 dark:bg-white/5 dark:text-white dark:outline-white/10"
                                >
                                @error('question')
                                    <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                                @enderror
                            </div>

                            <div>
                                <label for="answer" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Answer</label>
                                <textarea
                                    id="answer" name="answer" required rows="4" maxlength="2000"
                                    class="mt-1 block w-full rounded-md bg-white px-3 py-1.5 text-sm text-gray-900 outline-1 -outline-offset-1 outline-gray-300 focus:outline-2 focus:-outline-offset-2 focus:outline-indigo-600 dark:bg-white/5 dark:text-white dark:outline-white/10"
                                >{{ old('answer', $faqPrefill?->answer) }}</textarea>
                                @error('answer')
                                    <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                                @enderror
                            </div>

                            <button type="submit" class="rounded-md bg-gray-900 px-3 py-2 text-sm font-semibold text-white hover:bg-gray-700 dark:bg-white dark:text-gray-900 dark:hover:bg-gray-200">Publish as FAQ</button>
                        </form>
                    </section>
                @endif
            </x-seller.messaging.thread>
        </x-seller.list-detail>
    </div>
</x-layouts.seller>
