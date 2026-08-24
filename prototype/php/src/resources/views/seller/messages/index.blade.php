<x-layouts.seller title="Messages — Art Store seller">
    <h1 class="text-xl font-semibold">Messages</h1>

    <x-messaging.inbox :conversations="$conversations" :viewer="$viewer" show-route="seller.messages.show" />
</x-layouts.seller>
