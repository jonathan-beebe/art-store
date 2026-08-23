<x-layouts.admin title="Messages — Art Store admin">
    <h1 class="text-xl font-semibold">Messages</h1>

    <x-messaging.inbox :conversations="$conversations" :viewer="$viewer" show-route="admin.messages.show" />
</x-layouts.admin>
