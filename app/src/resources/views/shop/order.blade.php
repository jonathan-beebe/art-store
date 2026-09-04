<x-layouts.shop :title="'Order '.$order->id.' — Art Store'">
    <h1 class="font-display text-4xl leading-tight text-ink">Order {{ $order->id }}</h1>

    <p class="mt-3 text-lg text-ink-muted">
        {{ $order->status->label() }} · {{ $order->total() }}
        @if ($order->refunded_cents > 0)
            · {{ $order->refunded() }} refunded
        @endif
    </p>

    <div class="mt-6 flex flex-wrap gap-4">
        @visitorCan('cancel', $order)
            <form method="POST" action="{{ route('shop.order.cancel', $order) }}">
                @csrf
                <x-ui.button variant="secondary">
                    Cancel this order
                </x-ui.button>
            </form>
        @endvisitorCan

        <x-ui.button variant="secondary" :href="route('shop.support', ['order' => $order->id])">
            Contact Art Store about this order
        </x-ui.button>
    </div>

    @if ($awaitsPayment && ! $isPayable)
        <x-ui.alert tone="success" class="mt-10 max-w-xl">
            <p class="text-lg font-semibold">Check your email</p>
            <p class="mt-2 text-base">
                A link is on its way to {{ $order->email }}. Open it to verify your address and pay for this order.
            </p>
        </x-ui.alert>
    @endif

    @if ($isPayable)
        <section class="mt-10 max-w-xl rounded-card border border-line p-6">
            <h2 class="text-lg font-semibold text-ink">Payment</h2>

            @if ($payment?->decline_reason)
                <x-ui.alert tone="danger" class="mt-3">
                    {{ $payment->decline_reason->message() }} Try another card.
                </x-ui.alert>
            @endif

            <form method="POST" action="{{ route('shop.order.pay.submit', $order) }}" class="mt-4">
                @csrf
                <x-card-fields />

                <x-ui.button variant="primary" class="mt-8">
                    Pay {{ $order->total() }}
                </x-ui.button>
            </form>
        </section>
    @endif

    <div class="mt-14 grid gap-14 lg:grid-cols-[minmax(0,3fr)_minmax(0,2fr)]">
        <div>
            @foreach ($fulfillments as $fulfillment)
                <section class="border-t border-line py-8 first:border-t-0 first:pt-0">
                    <div class="flex flex-wrap items-baseline justify-between gap-4">
                        <h2 class="text-lg font-medium text-ink">{{ $fulfillment->seller->displayName() }}</h2>
                        <p class="text-base text-ink-muted">{{ $fulfillment->status->label() }}</p>
                    </div>

                    @if ($fulfillment->carrier)
                        <p class="mt-2 text-base text-ink-muted">
                            {{ $fulfillment->carrier }} · tracking {{ $fulfillment->tracking_number }}
                        </p>
                    @endif

                    @if ($fulfillment->refund)
                        <x-ui.alert tone="notice" class="mt-2 text-base">
                            {{ $fulfillment->refund->amount() }} refunded — {{ $fulfillment->refund->reason }}
                        </x-ui.alert>
                    @endif

                    <ul class="mt-4 space-y-4">
                        @foreach ($itemsBySeller[$fulfillment->seller_id] ?? [] as $item)
                            <li class="text-base text-ink">
                                <div class="flex items-baseline justify-between gap-6">
                                    <span>{{ $item->title }} × {{ $item->quantity }}</span>
                                    <span>{{ $item->lineTotal() }}</span>
                                </div>

                                <x-order-item-detail :item="$item" />
                            </li>
                        @endforeach
                    </ul>

                    <div class="mt-6 flex flex-wrap gap-4">
                        @visitorCan('confirmDelivery', $fulfillment)
                            <form method="POST" action="{{ route('shop.order.delivered', [$order, $fulfillment]) }}">
                                @csrf
                                <x-ui.button variant="secondary">
                                    Confirm delivery
                                </x-ui.button>
                            </form>
                        @endvisitorCan

                        <form method="POST" action="{{ route('shop.order.messages', [$order, $fulfillment]) }}">
                            @csrf
                            <x-ui.button variant="secondary">
                                Message the seller
                            </x-ui.button>
                        </form>
                    </div>
                </section>
            @endforeach
        </div>

        <aside>
            <h2 class="text-sm font-medium uppercase tracking-wide text-ink-faint">Shipping to</h2>
            <address class="mt-4 text-base not-italic leading-relaxed text-ink-muted">
                {{ $order->shipping_name }}<br>
                {{ $order->shipping_line1 }}<br>
                @if ($order->shipping_line2){{ $order->shipping_line2 }}<br>@endif
                {{ $order->shipping_city }}, {{ $order->shipping_region }}<br>
                {{ $order->shipping_postal_code }}<br>
                {{ $order->shipping_country }}
            </address>
        </aside>
    </div>
</x-layouts.shop>
