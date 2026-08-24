<x-layouts.admin :title="'Order '.$order->id.' — Art Store admin'">
    <div class="flex flex-wrap items-center gap-4">
        <h1 class="text-xl font-semibold">Order {{ $order->id }}</h1>
        <a href="{{ route('admin.orders.index') }}" class="ml-auto text-gray-700 underline">All orders</a>
    </div>

    @if ($order->isCancellable())
        <form method="POST" action="{{ route('admin.orders.cancel', $order) }}" class="mt-4">
            @csrf
            <button type="submit" class="rounded border border-gray-400 bg-white px-4 py-2 font-medium">Cancel this order</button>
            <span class="ml-2 text-gray-600">Nothing has been charged yet; the stock goes back on the storefront.</span>
        </form>
    @endif

    <dl class="mt-4 grid gap-3 rounded border border-gray-300 bg-white p-4 sm:grid-cols-2">
        <div>
            <dt class="text-gray-600">Customer</dt>
            <dd class="mt-1"><a href="{{ route('admin.customers.show', $order->customer) }}" class="underline">{{ $order->customer->displayName() }}</a></dd>
        </div>
        <div>
            <dt class="text-gray-600">Status</dt>
            <dd class="mt-1">{{ $order->status->label() }}</dd>
        </div>
        <div>
            <dt class="text-gray-600">Placed</dt>
            <dd class="mt-1">{{ $order->placed_at?->format('M j, Y g:ia') }}</dd>
        </div>
        <div>
            <dt class="text-gray-600">Total</dt>
            <dd class="mt-1 tabular-nums">{{ $order->total()->format() }}</dd>
        </div>
        <div>
            <dt class="text-gray-600">Refunded</dt>
            <dd class="mt-1 tabular-nums">{{ $order->refunded()->format() }}</dd>
        </div>
        <div>
            <dt class="text-gray-600">Email</dt>
            <dd class="mt-1">{{ $order->email ?? '—' }}</dd>
        </div>
        <div>
            <dt class="text-gray-600">Ship to</dt>
            <dd class="mt-1">
                {{ $order->shipping_name }}<br>
                {{ $order->shipping_line1 }}@if ($order->shipping_line2), {{ $order->shipping_line2 }}@endif<br>
                {{ $order->shipping_city }}, {{ $order->shipping_region }} {{ $order->shipping_postal_code }}<br>
                {{ $order->shipping_country }}
            </dd>
        </div>
    </dl>

    <section aria-labelledby="items-heading" class="mt-6">
        <h2 id="items-heading" class="font-semibold text-gray-700">Items</h2>

        <div class="mt-2 overflow-x-auto rounded border border-gray-300 bg-white">
            <table class="w-full text-left">
                <caption class="sr-only">Every line on this order</caption>
                <thead class="border-b border-gray-300 bg-gray-50">
                    <tr>
                        <th scope="col" class="px-4 py-2 font-semibold">Item</th>
                        <th scope="col" class="px-4 py-2 font-semibold">Seller</th>
                        <th scope="col" class="px-4 py-2 text-right font-semibold">Quantity</th>
                        <th scope="col" class="px-4 py-2 text-right font-semibold">Unit price</th>
                        <th scope="col" class="px-4 py-2 text-right font-semibold">Line total</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    @foreach ($order->items as $item)
                        <tr>
                            <th scope="row" class="px-4 py-2 font-normal">
                                <a href="{{ route('admin.listings.show', $item->listing_id) }}" class="underline">{{ $item->title }}</a>
                            </th>
                            <td class="px-4 py-2">
                                <a href="{{ route('admin.sellers.show', $item->seller) }}" class="underline">{{ $item->seller->displayName() }}</a>
                            </td>
                            <td class="px-4 py-2 text-right tabular-nums">{{ $item->quantity }}</td>
                            <td class="px-4 py-2 text-right tabular-nums">{{ $item->unitPrice()->format() }}</td>
                            <td class="px-4 py-2 text-right tabular-nums">{{ $item->lineTotal()->format() }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </section>

    <section aria-labelledby="payments-heading" class="mt-6">
        <h2 id="payments-heading" class="font-semibold text-gray-700">Payments</h2>

        @if ($order->payments->isEmpty())
            <x-admin.nothing>No payment attempts yet.</x-admin.nothing>
        @else
            <div class="mt-2 overflow-x-auto rounded border border-gray-300 bg-white">
                <table class="w-full text-left">
                    <caption class="sr-only">Every card attempt against this order</caption>
                    <thead class="border-b border-gray-300 bg-gray-50">
                        <tr>
                            <th scope="col" class="px-4 py-2 font-semibold">Processed</th>
                            <th scope="col" class="px-4 py-2 font-semibold">Status</th>
                            <th scope="col" class="px-4 py-2 font-semibold">Card</th>
                            <th scope="col" class="px-4 py-2 font-semibold">Decline reason</th>
                            <th scope="col" class="px-4 py-2 text-right font-semibold">Amount</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        @foreach ($order->payments as $payment)
                            <tr>
                                <th scope="row" class="px-4 py-2 font-normal">{{ $payment->processed_at?->format('M j, Y g:ia') }}</th>
                                <td class="px-4 py-2">{{ $payment->status->label() }}</td>
                                <td class="px-4 py-2">•••• {{ $payment->card_last_four }}</td>
                                <td class="px-4 py-2">{{ $payment->decline_reason?->message() ?? '—' }}</td>
                                <td class="px-4 py-2 text-right tabular-nums">{{ $payment->amount()->format() }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>

    <section aria-labelledby="fulfillments-heading" class="mt-6">
        <h2 id="fulfillments-heading" class="font-semibold text-gray-700">Fulfillments</h2>

        <x-admin.fulfillments-table :fulfillments="$order->fulfillments" :show-order="false" caption="Every seller's shipment on this order" />
    </section>

    <section aria-labelledby="refunds-heading" class="mt-6">
        <h2 id="refunds-heading" class="font-semibold text-gray-700">Refunds</h2>

        <x-admin.refunds-table :refunds="$order->refunds" caption="Every refund issued on this order" />
    </section>

    <section aria-labelledby="refund-actions-heading" class="mt-6">
        <h2 id="refund-actions-heading" class="font-semibold text-gray-700">Refund a fulfillment</h2>

        @foreach ($order->fulfillments as $fulfillment)
            <h3 class="mt-4 font-medium text-gray-700">{{ $fulfillment->seller->displayName() }}</h3>
            <x-admin.refund-form :fulfillment="$fulfillment" />
        @endforeach
    </section>
</x-layouts.admin>
