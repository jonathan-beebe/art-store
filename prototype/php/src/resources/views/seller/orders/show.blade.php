<x-layouts.seller :title="'Order '.$fulfillment->order_id.' — Art Store seller'">
    <div class="flex flex-wrap items-center gap-4">
        <h1 class="text-xl font-semibold">Order {{ $fulfillment->order_id }}</h1>
        <p class="text-gray-600 dark:text-gray-400">{{ $fulfillment->status->label() }}</p>

        <form method="POST" action="{{ route('seller.orders.messages', $fulfillment) }}" class="ml-auto">
            @csrf
            <button type="submit" class="text-gray-700 dark:text-gray-300 underline">Message the customer</button>
        </form>

        <a href="{{ route('seller.orders.index') }}" class="text-gray-700 dark:text-gray-300 underline">All orders</a>
    </div>

    <div class="mt-6 grid gap-6 lg:grid-cols-2">
        <section aria-labelledby="address-heading">
            <h2 id="address-heading" class="font-semibold text-gray-700 dark:text-gray-300">Ship to</h2>

            <address class="mt-2 rounded border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-900 p-4 not-italic">
                {{ $fulfillment->order->shipping_name }}<br>
                {{ $fulfillment->order->shipping_line1 }}<br>
                @if ($fulfillment->order->shipping_line2)
                    {{ $fulfillment->order->shipping_line2 }}<br>
                @endif
                {{ $fulfillment->order->shipping_city }}, {{ $fulfillment->order->shipping_region }}<br>
                {{ $fulfillment->order->shipping_postal_code }}<br>
                {{ $fulfillment->order->shipping_country }}
            </address>
        </section>

        <section aria-labelledby="items-heading">
            <h2 id="items-heading" class="font-semibold text-gray-700 dark:text-gray-300">Your items</h2>

            <div class="mt-2 overflow-x-auto rounded border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-900">
                <table class="w-full text-left">
                    <caption class="sr-only">Items in this order that belong to you</caption>
                    <thead class="border-b border-gray-300 dark:border-gray-700 bg-gray-50 dark:bg-gray-800/50">
                        <tr>
                            <th scope="col" class="px-4 py-2 font-semibold">Item</th>
                            <th scope="col" class="px-4 py-2 text-right font-semibold">Qty</th>
                            <th scope="col" class="px-4 py-2 text-right font-semibold">Unit price</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-gray-800">
                        @foreach ($fulfillment->order->items as $item)
                            <tr>
                                <th scope="row" class="px-4 py-2 font-normal">{{ $item->title }}</th>
                                <td class="px-4 py-2 text-right tabular-nums">{{ $item->quantity }}</td>
                                <td class="px-4 py-2 text-right tabular-nums">{{ $item->unitPrice() }}</td>
                            </tr>
                            @if ($item->hasVariant())
                                <tr>
                                    <td colspan="3" class="px-4 pb-3">
                                        <x-order-item-detail :item="$item" />
                                    </td>
                                </tr>
                            @endif
                        @endforeach
                    </tbody>
                    <tfoot class="border-t border-gray-300 dark:border-gray-700">
                        <tr>
                            <th scope="row" class="px-4 py-2 text-left font-semibold">Net to you</th>
                            <td colspan="2" class="px-4 py-2 text-right font-semibold tabular-nums">{{ $fulfillment->net()->format() }}</td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </section>
    </div>

    @if ($fulfillment->refund)
        <section aria-labelledby="refund-heading" class="mt-6 max-w-xl">
            <h2 id="refund-heading" class="font-semibold text-gray-700 dark:text-gray-300">Refund</h2>

            <dl class="mt-2 rounded border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-900 p-4">
                <dt class="text-gray-600 dark:text-gray-400">Amount</dt>
                <dd class="mt-1 tabular-nums">{{ $fulfillment->refund->amount()->format() }}</dd>
                <dt class="mt-3 text-gray-600 dark:text-gray-400">Reason</dt>
                <dd class="mt-1">{{ $fulfillment->refund->reason }}</dd>
                <dt class="mt-3 text-gray-600 dark:text-gray-400">Issued by</dt>
                <dd class="mt-1">{{ $fulfillment->refund->issuerLabel() }}</dd>
            </dl>
        </section>
    @endif

    <section aria-labelledby="shipment-heading" class="mt-6 max-w-xl">
        <h2 id="shipment-heading" class="font-semibold text-gray-700 dark:text-gray-300">Shipment</h2>

        @can('ship', $fulfillment)
            <form method="POST" action="{{ route('seller.orders.ship', $fulfillment->id) }}"
                  class="mt-2 rounded border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-900 p-4">
                @csrf

                <fieldset>
                    <legend class="font-medium text-gray-700 dark:text-gray-300">Mark shipped</legend>

                    <div class="mt-2">
                        <label for="carrier" class="block font-medium text-gray-700 dark:text-gray-300">Carrier</label>
                        <input id="carrier" name="carrier" type="text" required maxlength="255" value="{{ old('carrier') }}"
                               class="mt-1 block w-full rounded border border-gray-400 dark:border-gray-600 px-3 py-2">
                        @error('carrier')
                            <p class="mt-1 text-red-700 dark:text-red-400">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="mt-4">
                        <label for="tracking_number" class="block font-medium text-gray-700 dark:text-gray-300">Tracking number</label>
                        <input id="tracking_number" name="tracking_number" type="text" required maxlength="255" value="{{ old('tracking_number') }}"
                               class="mt-1 block w-full rounded border border-gray-400 dark:border-gray-600 px-3 py-2">
                        @error('tracking_number')
                            <p class="mt-1 text-red-700 dark:text-red-400">{{ $message }}</p>
                        @enderror
                    </div>
                </fieldset>

                <button type="submit" class="mt-4 rounded bg-gray-900 dark:bg-gray-100 px-4 py-2 font-medium text-white dark:text-gray-900">Mark shipped</button>
            </form>
        @else
            <dl class="mt-2 rounded border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-900 p-4">
                <dt class="text-gray-600 dark:text-gray-400">Carrier</dt>
                <dd class="mt-1">{{ $fulfillment->carrier }}</dd>
                <dt class="mt-3 text-gray-600 dark:text-gray-400">Tracking number</dt>
                <dd class="mt-1">{{ $fulfillment->tracking_number }}</dd>
                <dt class="mt-3 text-gray-600 dark:text-gray-400">Shipped</dt>
                <dd class="mt-1">{{ $fulfillment->shipped_at?->format('M j, Y g:ia') }}</dd>
                @if ($fulfillment->delivered_at)
                    <dt class="mt-3 text-gray-600 dark:text-gray-400">Delivered</dt>
                    <dd class="mt-1">{{ $fulfillment->delivered_at->format('M j, Y g:ia') }}</dd>
                @endif
            </dl>
        @endcan
    </section>

    @can('decline', $fulfillment)
        <section aria-labelledby="decline-heading" class="mt-6 max-w-xl">
            <h2 id="decline-heading" class="font-semibold text-gray-700 dark:text-gray-300">Decline</h2>

            <form method="POST" action="{{ route('seller.orders.decline', $fulfillment->id) }}"
                  class="mt-2 rounded border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-900 p-4">
                @csrf

                <p class="text-gray-600 dark:text-gray-400">
                    Declining refunds {{ $fulfillment->subtotal()->format() }} to the customer and puts your pieces
                    back on the storefront.
                </p>

                <div class="mt-4">
                    <label for="reason" class="block font-medium text-gray-700 dark:text-gray-300">Reason</label>
                    <textarea id="reason" name="reason" required minlength="1" maxlength="500" rows="3"
                              class="mt-1 block w-full rounded border border-gray-400 dark:border-gray-600 px-3 py-2">{{ old('reason') }}</textarea>
                    @error('reason')
                        <p class="mt-1 text-red-700 dark:text-red-400">{{ $message }}</p>
                    @enderror
                </div>

                <button type="submit" class="mt-4 rounded border border-gray-400 dark:border-gray-600 bg-white dark:bg-gray-900 px-4 py-2 font-medium">Decline and refund</button>
            </form>
        </section>
    @endcan
</x-layouts.seller>
