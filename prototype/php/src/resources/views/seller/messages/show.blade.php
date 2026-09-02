<x-layouts.seller :title="$conversation->counterpartName($viewer).' — Art Store seller'" :bleed="true">
    <div class="h-[calc(100dvh-4rem)] overflow-hidden">
        <x-seller.list-detail mobile="detail">
            <x-slot:listHeader>
                <div class="flex items-baseline justify-between gap-x-2">
                    <h2 class="text-base font-semibold text-gray-900 dark:text-white">Messages</h2>
                    <a href="{{ route('seller.support') }}" class="rounded text-sm font-medium text-indigo-600 hover:text-indigo-500 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600 dark:text-indigo-400 dark:hover:text-indigo-300">New conversation</a>
                </div>
                <div class="mt-3">
                    <x-seller.messaging.filters :filter="$filter" :status="$status" :counts="$filterCounts" />
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
                    :filter="$filter"
                    :status="$status"
                />
            </x-slot:list>

            <x-seller.messaging.thread
                :conversation="$conversation"
                :viewer="$viewer"
                index-route="seller.messages.index"
                store-route="seller.messages.store"
                :reply-to="$replyTo"
                :faq-prefill="$faqPrefill"
                :filter="$filter"
                :status="$status"
            />
        </x-seller.list-detail>
    </div>
</x-layouts.seller>
