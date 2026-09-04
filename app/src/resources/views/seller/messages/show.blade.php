<x-layouts.seller :title="$conversation->counterpartName($viewer).' — Art Store seller'" :bleed="true">
    <div class="h-[calc(100dvh-4rem)] overflow-hidden">
        <x-seller.list-detail mobile="detail">
            <x-slot:listHeader>
                <h2 class="text-base font-semibold text-gray-900 dark:text-white">Messages</h2>
                <div class="mt-3">
                    <x-messaging.inbox-tabs
                        accent="indigo"
                        :domain="$domain"
                        index-route="seller.messages.index"
                        :domains="['all' => 'All', 'buyers' => 'Buyers', 'support' => 'Support']"
                    />
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
                    :domain="$domain"
                />
            </x-slot:list>

            <x-seller.messaging.thread
                :conversation="$conversation"
                :viewer="$viewer"
                index-route="seller.messages.index"
                store-route="seller.messages.store"
                :reply-to="$replyTo"
                :faq-prefill="$faqPrefill"
                :domain="$domain"
                :context="$context"
            />
        </x-seller.list-detail>
    </div>
</x-layouts.seller>
