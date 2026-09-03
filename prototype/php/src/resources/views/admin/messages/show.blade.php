<x-layouts.admin :title="$conversation->counterpartName($viewer).' — Art Store admin'" mode="detail">
    <x-slot:cells>
        <div class="flex flex-col gap-3 border-b border-stone-200 p-3 dark:border-stone-800">
            <h1 class="text-sm font-semibold">Messages</h1>
            <x-messaging.inbox-filters
                accent="stone"
                :query="$query"
                index-route="admin.messages.index"
                :domains="['all' => 'All', 'sellers' => 'Sellers', 'customers' => 'Customers']"
                :statuses="['open' => 'Open', 'resolved' => 'Resolved', 'needs-reply' => 'Needs reply']"
                :default-statuses="\App\Http\Requests\Admin\MessagesQueryRequest::DEFAULT_STATUSES"
                :needs-reply-count="$needsReplyCount"
            />
        </div>
        <div class="flex-1 overflow-y-auto p-2">
            <x-messaging.inbox :conversations="$cellConversations" :viewer="$viewer" show-route="admin.messages.show" :selected="$conversation" :query="$query" index-route="admin.messages.index" />
        </div>
        <x-admin.cell-footer :shown="$cellConversations->count()" :total="$cellConversationsTotal" :route="route('admin.messages.index', $query->toRouteParams())" />
    </x-slot:cells>

    <x-messaging.thread :conversation="$conversation" :viewer="$viewer" index-route="admin.messages.index" store-route="admin.messages.store" :reply-to="$replyTo" :query="$query" />
</x-layouts.admin>
