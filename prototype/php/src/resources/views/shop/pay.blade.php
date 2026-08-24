<x-layouts.shop :title="'Pay for order '.$order->id.' — Art Store'">
    <h1 class="text-4xl font-semibold tracking-tight">Pay for order {{ $order->id }}</h1>

    <p class="mt-3 text-lg text-neutral-600">
        {{ $order->total() }} to {{ $order->email }}
    </p>

    @if ($payment?->decline_reason)
        <p role="alert" class="mt-8 max-w-xl rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-red-900">
            {{ $payment->decline_reason->message() }} Try another card.
        </p>
    @endif

    <form method="POST" action="{{ route('shop.order.pay.submit', $order) }}" class="mt-8 max-w-xl">
        @csrf
        <x-card-fields />

        <button type="submit" class="mt-10 rounded-full bg-neutral-900 px-8 py-3 text-base font-medium text-white">
            Pay {{ $order->total() }}
        </button>
    </form>
</x-layouts.shop>
