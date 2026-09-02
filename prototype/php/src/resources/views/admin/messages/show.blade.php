<x-layouts.admin :title="$conversation->counterpartName($viewer).' — Art Store admin'" mode="detail">
    <x-slot:cells>
        <div class="flex items-baseline gap-2 border-b border-stone-200 p-3 dark:border-stone-800">
            <h1 class="text-sm font-semibold">Messages</h1>
            <span class="text-xs text-stone-500 dark:text-stone-400">{{ $cellConversationsTotal }}</span>
        </div>
        <div class="flex-1 overflow-y-auto p-2">
            <x-messaging.inbox :conversations="$cellConversations" :viewer="$viewer" show-route="admin.messages.show" :selected="$conversation" />
        </div>
        <x-admin.cell-footer :shown="$cellConversations->count()" :total="$cellConversationsTotal" :route="route('admin.messages.index')" />
    </x-slot:cells>

    <x-messaging.thread :conversation="$conversation" :viewer="$viewer" index-route="admin.messages.index" store-route="admin.messages.store" :reply-to="$replyTo" />
</x-layouts.admin>
