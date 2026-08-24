<x-layouts.shop :title="'Order #'.$order->id.' — Art Store'">
    <h1 class="text-4xl font-semibold tracking-tight">Order #{{ $order->id }}</h1>

    <p class="mt-3 text-lg text-neutral-600">
        {{ $order->status->label() }} · {{ $order->total() }}
    </p>

    @if ($awaitsPayment && ! $isPayable)
        <div role="status" class="mt-10 max-w-xl rounded-2xl border border-green-200 bg-green-50 p-6 text-green-900">
            <p class="text-lg font-semibold">Check your email</p>
            <p class="mt-2 text-base">
                A link is on its way to {{ $order->email }}. Open it to verify your address and pay for this order.
            </p>
        </div>
    @endif

    @if ($isPayable)
        <section class="mt-10 max-w-xl rounded-2xl border border-neutral-200 p-6">
            <h2 class="text-lg font-semibold">Payment</h2>

            @if ($payment?->decline_reason)
                <p role="alert" class="mt-3 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-red-900">
                    {{ $payment->decline_reason->message() }} Try another card.
                </p>
            @endif

            <form method="POST" action="{{ route('shop.order.pay.submit', $order) }}" class="mt-4">
                @csrf
                <x-card-fields />

                <button type="submit" class="mt-8 rounded-full bg-neutral-900 px-8 py-3 text-base font-medium text-white">
                    Pay {{ $order->total() }}
                </button>
            </form>
        </section>
    @endif

    <div class="mt-14 grid gap-14 lg:grid-cols-[minmax(0,3fr)_minmax(0,2fr)]">
        <div>
            @foreach ($fulfillments as $fulfillment)
                <section class="border-t border-neutral-100 py-8 first:border-t-0 first:pt-0">
                    <div class="flex flex-wrap items-baseline justify-between gap-4">
                        <h2 class="text-lg font-medium">{{ $fulfillment->seller->displayName() }}</h2>
                        <p class="text-base text-neutral-600">{{ $fulfillment->status->label() }}</p>
                    </div>

                    @if ($fulfillment->carrier)
                        <p class="mt-2 text-base text-neutral-600">
                            {{ $fulfillment->carrier }} · tracking {{ $fulfillment->tracking_number }}
                        </p>
                    @endif

                    <ul class="mt-4 space-y-2">
                        @foreach ($itemsBySeller[$fulfillment->seller_id] ?? [] as $item)
                            <li class="flex items-baseline justify-between gap-6 text-base">
                                <span>{{ $item->title }} × {{ $item->quantity }}</span>
                                <span>{{ $item->lineTotal() }}</span>
                            </li>
                        @endforeach
                    </ul>

                    <div class="mt-6 flex flex-wrap gap-4">
                        @visitorCan('confirmDelivery', $fulfillment)
                            <form method="POST" action="{{ route('shop.order.delivered', [$order, $fulfillment]) }}">
                                @csrf
                                <button type="submit" class="rounded-full border border-neutral-300 px-6 py-2 text-base font-medium hover:border-neutral-900">
                                    Confirm delivery
                                </button>
                            </form>
                        @endvisitorCan

                        <form method="POST" action="{{ route('shop.order.messages', [$order, $fulfillment]) }}">
                            @csrf
                            <button type="submit" class="rounded-full border border-neutral-300 px-6 py-2 text-base font-medium hover:border-neutral-900">
                                Message the seller
                            </button>
                        </form>
                    </div>
                </section>
            @endforeach
        </div>

        <aside>
            <h2 class="text-sm font-medium uppercase tracking-wide text-neutral-500">Shipping to</h2>
            <address class="mt-4 text-base not-italic leading-relaxed text-neutral-700">
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
