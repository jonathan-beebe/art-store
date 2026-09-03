<x-layouts.admin title="Messages — Art Store admin" mode="list" empty-detail-prompt="Choose a conversation to read it.">
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
            <x-messaging.inbox :conversations="$conversations" :viewer="$viewer" show-route="admin.messages.show" :query="$query" index-route="admin.messages.index" />
        </div>
        <x-admin.cell-footer :shown="$conversations->count()" :total="$conversationsTotal" :route="route('admin.messages.index', $query->toRouteParams())" />
    </x-slot:cells>

    <h1 class="text-xl font-semibold">Messages</h1>

    <div class="mt-4">
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

    {{-- Below `lg`, no cells pane wraps the list — this card matches the
         rest of the admin's below-`lg` index cards (customers, orders, ...). --}}
    <div class="mt-4 overflow-hidden rounded border border-stone-300 bg-white dark:border-stone-700 dark:bg-stone-900">
        <x-messaging.inbox :conversations="$conversations" :viewer="$viewer" show-route="admin.messages.show" :query="$query" index-route="admin.messages.index" />
    </div>
</x-layouts.admin>
