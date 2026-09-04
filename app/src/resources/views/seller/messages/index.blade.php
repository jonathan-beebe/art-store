<x-layouts.seller title="Messages — Art Store seller" :bleed="true">
    <div class="h-[calc(100dvh-4rem)] overflow-hidden">
        <x-seller.list-detail>
            <x-slot:listHeader>
                <h1 class="text-base font-semibold text-gray-900 dark:text-white">Messages</h1>
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
                    :conversations="$conversations"
                    :viewer="$viewer"
                    show-route="seller.messages.show"
                    :total="$conversationsTotal"
                    index-route="seller.messages.index"
                    :domain="$domain"
                />
            </x-slot:list>

            <div class="flex h-full items-center justify-center p-8">
                <p class="text-sm text-gray-500 dark:text-gray-500">Choose a conversation to read it.</p>
            </div>
        </x-seller.list-detail>
    </div>
</x-layouts.seller>
