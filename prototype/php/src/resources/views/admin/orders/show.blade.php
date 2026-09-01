<x-layouts.admin :title="'Order '.$order->id.' — Art Store admin'" mode="detail">
    @php
        $tint = match ($order->status) {
            \App\Domain\Orders\OrderStatus::Cancelled, \App\Domain\Orders\OrderStatus::PaymentFailed, \App\Domain\Orders\OrderStatus::Refunded => 'red',
            \App\Domain\Orders\OrderStatus::PendingVerification, \App\Domain\Orders\OrderStatus::AwaitingPayment => 'yellow',
            \App\Domain\Orders\OrderStatus::Paid, \App\Domain\Orders\OrderStatus::PartiallyShipped, \App\Domain\Orders\OrderStatus::Shipped, \App\Domain\Orders\OrderStatus::Delivered => 'green',
        };
    @endphp

    <x-slot:cells>
        <div class="flex items-baseline gap-2 border-b border-stone-200 px-6 py-4 dark:border-white/10">
            <h1 class="text-sm font-semibold text-stone-900 dark:text-stone-100">Orders</h1>
            <span class="text-xs text-stone-500 dark:text-stone-400">{{ $cellOrdersTotal }}</span>
        </div>
        <div class="flex-1 overflow-y-auto">
            <x-admin.orders-cells :orders="$cellOrders" :selected="$order" />
        </div>
        <x-admin.cell-footer :shown="$cellOrders->count()" :total="$cellOrdersTotal" :route="route('admin.orders.index')" />
    </x-slot:cells>

    <x-admin.back-link :route="route('admin.orders.index')" label="Orders" />

    <div class="flex flex-wrap items-start justify-between gap-4">
        <div>
            <p class="text-xs text-stone-500 dark:text-stone-400">{{ $order->id }} &middot; placed {{ $order->placed_at?->format('M j, Y') }}</p>
            <h1 class="mt-1 flex flex-wrap items-center gap-3 text-lg font-semibold text-stone-900 dark:text-stone-100">
                {{ $order->customer->displayName() }}
                <x-admin.status-pill :tint="$tint">{{ $order->status->label() }}</x-admin.status-pill>
            </h1>
        </div>

        <a href="{{ route('admin.orders.index') }}" class="hidden text-sm text-stone-600 dark:text-stone-400 underline sm:inline">All orders</a>
    </div>

    @if ($order->isCancellable())
        <form method="POST" action="{{ route('admin.orders.cancel', $order) }}" class="mt-4">
            @csrf
            <button type="submit" class="block w-full rounded-md bg-white px-4 py-2 text-center font-medium text-stone-900 inset-ring inset-ring-stone-300 hover:bg-stone-50 dark:bg-white/10 dark:text-white dark:inset-ring-white/10 dark:hover:bg-white/20 sm:inline-block sm:w-auto">Cancel this order</button>
            <span class="mt-2 block text-stone-600 dark:text-stone-400 sm:ml-2 sm:mt-0 sm:inline">Nothing has been charged yet; the stock goes back on the storefront.</span>
        </form>
    @endif

    <dl class="mt-6 grid grid-cols-1 gap-x-8 border-t border-stone-200 dark:border-white/10 sm:grid-cols-2">
        <div class="flex justify-between gap-4 border-b border-stone-200 dark:border-white/10 py-3">
            <dt class="font-medium text-stone-900 dark:text-stone-100">Customer</dt>
            <dd class="text-right text-stone-600 dark:text-stone-400"><a href="{{ route('admin.customers.show', $order->customer) }}" class="underline">{{ $order->customer->displayName() }}</a></dd>
        </div>
        <div class="flex justify-between gap-4 border-b border-stone-200 dark:border-white/10 py-3">
            <dt class="font-medium text-stone-900 dark:text-stone-100">Placed</dt>
            <dd class="text-right text-stone-600 dark:text-stone-400">{{ $order->placed_at?->format('M j, Y g:ia') }}</dd>
        </div>
        <div class="flex justify-between gap-4 border-b border-stone-200 dark:border-white/10 py-3">
            <dt class="font-medium text-stone-900 dark:text-stone-100">Total</dt>
            <dd class="text-right tabular-nums text-stone-600 dark:text-stone-400">{{ $order->total()->format() }}</dd>
        </div>
        <div class="flex justify-between gap-4 border-b border-stone-200 dark:border-white/10 py-3">
            <dt class="font-medium text-stone-900 dark:text-stone-100">Refunded</dt>
            <dd class="text-right tabular-nums text-stone-600 dark:text-stone-400">{{ $order->refunded()->format() }}</dd>
        </div>
        <div class="flex justify-between gap-4 border-b border-stone-200 dark:border-white/10 py-3">
            <dt class="font-medium text-stone-900 dark:text-stone-100">Email</dt>
            <dd class="text-right text-stone-600 dark:text-stone-400">{{ $order->email ?? '—' }}</dd>
        </div>
        <div class="flex justify-between gap-4 border-b border-stone-200 dark:border-white/10 py-3">
            <dt class="font-medium text-stone-900 dark:text-stone-100">Ship to</dt>
            <dd class="text-right text-stone-600 dark:text-stone-400">
                {{ $order->shipping_name }}<br>
                {{ $order->shipping_line1 }}@if ($order->shipping_line2), {{ $order->shipping_line2 }}@endif<br>
                {{ $order->shipping_city }}, {{ $order->shipping_region }} {{ $order->shipping_postal_code }}<br>
                {{ $order->shipping_country }}
            </dd>
        </div>
    </dl>

    <section aria-labelledby="items-heading" class="mt-6">
        <h2 id="items-heading" class="font-semibold text-stone-700 dark:text-stone-300">Items</h2>

        <div class="mt-2 overflow-x-auto rounded-md border border-stone-300 dark:border-stone-700 bg-white dark:bg-stone-900">
            <table class="w-full text-left">
                <caption class="sr-only">Every line on this order</caption>
                <thead class="border-b border-stone-300 dark:border-stone-700 bg-stone-50 dark:bg-stone-800/50">
                    <tr>
                        <th scope="col" class="px-4 py-2 font-semibold">Item</th>
                        <th scope="col" class="px-4 py-2 font-semibold">Seller</th>
                        <th scope="col" class="px-4 py-2 text-right font-semibold">Quantity</th>
                        <th scope="col" class="px-4 py-2 text-right font-semibold">Unit price</th>
                        <th scope="col" class="px-4 py-2 text-right font-semibold">Line total</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-stone-200 dark:divide-stone-800">
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
                        @if ($item->hasVariant())
                            <tr>
                                <td colspan="5" class="px-4 pb-3">
                                    <x-order-item-detail :item="$item" />
                                </td>
                            </tr>
                        @endif
                    @endforeach
                </tbody>
            </table>
        </div>
    </section>

    <section aria-labelledby="payments-heading" class="mt-6">
        <h2 id="payments-heading" class="font-semibold text-stone-700 dark:text-stone-300">Payments</h2>

        @if ($order->payments->isEmpty())
            <x-admin.nothing>No payment attempts yet.</x-admin.nothing>
        @else
            <div class="mt-2 overflow-x-auto rounded-md border border-stone-300 dark:border-stone-700 bg-white dark:bg-stone-900">
                <table class="w-full text-left">
                    <caption class="sr-only">Every card attempt against this order</caption>
                    <thead class="border-b border-stone-300 dark:border-stone-700 bg-stone-50 dark:bg-stone-800/50">
                        <tr>
                            <th scope="col" class="px-4 py-2 font-semibold">Processed</th>
                            <th scope="col" class="px-4 py-2 font-semibold">Status</th>
                            <th scope="col" class="px-4 py-2 font-semibold">Card</th>
                            <th scope="col" class="px-4 py-2 font-semibold">Decline reason</th>
                            <th scope="col" class="px-4 py-2 text-right font-semibold">Amount</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-stone-200 dark:divide-stone-800">
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
        <h2 id="fulfillments-heading" class="font-semibold text-stone-700 dark:text-stone-300">Fulfillments</h2>

        <x-admin.fulfillments-table :fulfillments="$order->fulfillments" :show-order="false" caption="Every seller's shipment on this order" />
    </section>

    <section aria-labelledby="refunds-heading" class="mt-6">
        <h2 id="refunds-heading" class="font-semibold text-stone-700 dark:text-stone-300">Refunds</h2>

        <x-admin.refunds-table :refunds="$order->refunds" caption="Every refund issued on this order" />
    </section>

    <section aria-labelledby="refund-actions-heading" class="mt-6">
        <h2 id="refund-actions-heading" class="font-semibold text-stone-700 dark:text-stone-300">Refund a fulfillment</h2>

        @foreach ($order->fulfillments as $fulfillment)
            <h3 class="mt-4 font-medium text-stone-700 dark:text-stone-300">{{ $fulfillment->seller->displayName() }}</h3>
            <x-admin.refund-form :fulfillment="$fulfillment" />
        @endforeach
    </section>
</x-layouts.admin>
