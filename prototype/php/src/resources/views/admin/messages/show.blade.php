<x-layouts.admin :title="$conversation->counterpartName($viewer).' — Art Store admin'" mode="detail">
    <x-slot:cells>
        <div class="flex flex-col gap-3 border-b border-stone-200 p-3 dark:border-stone-800">
            <div class="flex items-baseline gap-2">
                <h1 class="text-sm font-semibold">Messages</h1>
                <span class="text-xs text-stone-500 dark:text-stone-400">{{ $cellConversationsTotal }}</span>
            </div>
            <x-messaging.filter-chips :filter="$filter" :status="$status" />
        </div>
        <div class="flex-1 overflow-y-auto p-2">
            <x-messaging.inbox :conversations="$cellConversations" :viewer="$viewer" show-route="admin.messages.show" :selected="$conversation" :filter="$filter" :status="$status" />
        </div>
        <x-admin.cell-footer :shown="$cellConversations->count()" :total="$cellConversationsTotal" :route="route('admin.messages.index', ['filter' => $filter, 'status' => $status])" />
    </x-slot:cells>

    <x-messaging.thread :conversation="$conversation" :viewer="$viewer" index-route="admin.messages.index" store-route="admin.messages.store" :reply-to="$replyTo" :filter="$filter" :status="$status" />
</x-layouts.admin>
