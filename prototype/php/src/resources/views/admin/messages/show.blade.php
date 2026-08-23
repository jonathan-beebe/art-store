<x-layouts.admin :title="$conversation->counterpartName($viewer).' — Art Store admin'">
    <x-messaging.thread :conversation="$conversation" :viewer="$viewer" index-route="admin.messages.index" store-route="admin.messages.store" />
</x-layouts.admin>
