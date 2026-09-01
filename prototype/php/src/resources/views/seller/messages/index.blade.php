<x-layouts.seller title="Messages — Art Store seller">
    {{-- Cancels the layout's `<main>` padding so the list-detail pane fills
         exactly the viewport height left under the sticky top bar. --}}
    <div class="-mx-4 -my-6 h-[calc(100dvh-4rem)] sm:-mx-6 lg:-mx-8">
        <x-seller.list-detail>
            <x-slot:listHeader>
                <div class="flex items-baseline justify-between gap-x-2">
                    <h1 class="text-base font-semibold text-gray-900 dark:text-white">Messages</h1>
                    <span class="text-xs text-gray-500 dark:text-gray-400">{{ $conversationsTotal }}</span>
                </div>
            </x-slot:listHeader>

            <x-slot:list>
                <x-seller.messaging.inbox
                    :conversations="$conversations"
                    :viewer="$viewer"
                    show-route="seller.messages.show"
                    :total="$conversationsTotal"
                    index-route="seller.messages.index"
                />
            </x-slot:list>

            <div class="flex h-full items-center justify-center p-8">
                <p class="text-sm text-gray-500 dark:text-gray-500">Choose a conversation to read it.</p>
            </div>
        </x-seller.list-detail>
    </div>
</x-layouts.seller>
