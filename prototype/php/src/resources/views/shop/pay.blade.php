<x-layouts.shop :title="'Pay for order '.$order->id.' — Art Store'">
    <h1 class="font-display text-4xl leading-tight text-ink">Pay for order {{ $order->id }}</h1>

    <p class="mt-3 text-lg text-ink-muted">
        {{ $order->total() }} to {{ $order->email }}
    </p>

    @if ($payment?->decline_reason)
        <x-ui.alert tone="danger" class="mt-8 max-w-xl">
            {{ $payment->decline_reason->message() }} Try another card.
        </x-ui.alert>
    @endif

    @if (count($blocked) > 0)
        <x-ui.alert tone="danger" class="mt-8 max-w-xl">
            <p>This order can no longer be completed:</p>
            <ul class="mt-2 list-disc pl-5">
                @foreach ($blocked as $line)
                    <li>{{ $line->title }} — {{ $line->reason->notice() }}</li>
                @endforeach
            </ul>
        </x-ui.alert>
    @endif

    <form method="POST" action="{{ route('shop.order.pay.submit', $order) }}" class="mt-8 max-w-xl">
        @csrf
        <x-card-fields />

        <x-ui.button variant="primary" class="mt-10">
            Pay {{ $order->total() }}
        </x-ui.button>
    </form>
</x-layouts.shop>
