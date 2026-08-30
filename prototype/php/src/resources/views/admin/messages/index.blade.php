<x-layouts.admin title="Messages — Art Store admin" mode="list" empty-detail-prompt="Choose a conversation to read it.">
    <x-slot:cells>
        <div class="flex items-baseline gap-2 border-b border-gray-200 p-3 dark:border-gray-800">
            <h1 class="text-sm font-semibold">Messages</h1>
            <span class="text-xs text-gray-500 dark:text-gray-400">{{ $conversationsTotal }}</span>
        </div>
        <div class="flex-1 overflow-y-auto p-2">
            <x-messaging.inbox :conversations="$conversations" :viewer="$viewer" show-route="admin.messages.show" />
        </div>
        <x-admin.cell-footer :shown="$conversations->count()" :total="$conversationsTotal" :route="route('admin.messages.index')" />
    </x-slot:cells>

    <h1 class="text-xl font-semibold">Messages</h1>

    <x-messaging.inbox :conversations="$conversations" :viewer="$viewer" show-route="admin.messages.show" />
</x-layouts.admin>
