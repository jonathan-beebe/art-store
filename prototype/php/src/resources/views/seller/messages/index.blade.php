<x-layouts.seller title="Messages — Art Store seller" :bleed="true">
    <div class="h-[calc(100dvh-4rem)] overflow-hidden">
        <x-seller.list-detail>
            <x-slot:listHeader>
                <div class="flex items-baseline justify-between gap-x-2">
                    <h1 class="text-base font-semibold text-gray-900 dark:text-white">Messages</h1>
                    <a href="{{ route('seller.support') }}" class="rounded text-sm font-medium text-indigo-600 hover:text-indigo-500 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600 dark:text-indigo-400 dark:hover:text-indigo-300">New conversation</a>
                </div>
                <div class="mt-3">
                    <x-seller.messaging.filters :filter="$filter" :status="$status" :counts="$filterCounts" />
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
