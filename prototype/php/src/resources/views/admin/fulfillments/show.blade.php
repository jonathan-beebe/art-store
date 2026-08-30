<x-layouts.admin :title="'Fulfillment '.$fulfillment->id.' — Art Store admin'" mode="detail">
    <x-slot:cells>
        <div class="flex items-baseline gap-2 border-b border-gray-200 p-3 dark:border-gray-800">
            <h1 class="text-sm font-semibold">Fulfillments</h1>
            <span class="text-xs text-gray-500 dark:text-gray-400">{{ $cellFulfillmentsTotal }}</span>
        </div>
        <div class="flex-1 overflow-y-auto">
            <x-admin.fulfillments-cells :fulfillments="$cellFulfillments" :selected="$fulfillment" />
        </div>
        <x-admin.cell-footer :shown="$cellFulfillments->count()" :total="$cellFulfillmentsTotal" :route="route('admin.fulfillments.index')" />
    </x-slot:cells>

    <x-admin.back-link :route="route('admin.fulfillments.index')" label="Fulfillments" />

    <div class="flex flex-wrap items-center gap-4">
        <h1 class="text-xl font-semibold">Fulfillment {{ $fulfillment->id }}</h1>
        <a href="{{ route('admin.fulfillments.index') }}" class="ml-auto hidden text-gray-700 dark:text-gray-300 underline sm:inline">All fulfillments</a>
    </div>

    <dl class="mt-4 grid gap-3 rounded border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-900 p-4 sm:grid-cols-2">
        <div>
            <dt class="text-gray-600 dark:text-gray-400">Order</dt>
            <dd class="mt-1"><a href="{{ route('admin.orders.show', $fulfillment->order) }}" class="underline">{{ $fulfillment->order->id }}</a></dd>
        </div>
        <div>
            <dt class="text-gray-600 dark:text-gray-400">Customer</dt>
            <dd class="mt-1"><a href="{{ route('admin.customers.show', $fulfillment->order->customer) }}" class="underline">{{ $fulfillment->order->customer->displayName() }}</a></dd>
        </div>
        <div>
            <dt class="text-gray-600 dark:text-gray-400">Seller</dt>
            <dd class="mt-1"><a href="{{ route('admin.sellers.show', $fulfillment->seller) }}" class="underline">{{ $fulfillment->seller->displayName() }}</a></dd>
        </div>
        <div>
            <dt class="text-gray-600 dark:text-gray-400">Status</dt>
            <dd class="mt-1">{{ $fulfillment->status->label() }}</dd>
        </div>
        <div>
            <dt class="text-gray-600 dark:text-gray-400">Carrier</dt>
            <dd class="mt-1">{{ $fulfillment->carrier ?? '—' }}</dd>
        </div>
        <div>
            <dt class="text-gray-600 dark:text-gray-400">Tracking</dt>
            <dd class="mt-1">{{ $fulfillment->tracking_number ?? '—' }}</dd>
        </div>
        <div>
            <dt class="text-gray-600 dark:text-gray-400">Shipped</dt>
            <dd class="mt-1">{{ $fulfillment->shipped_at?->format('M j, Y g:ia') ?? '—' }}</dd>
        </div>
        <div>
            <dt class="text-gray-600 dark:text-gray-400">Delivered</dt>
            <dd class="mt-1">{{ $fulfillment->delivered_at?->format('M j, Y g:ia') ?? '—' }}</dd>
        </div>
    </dl>

    <section aria-labelledby="money-heading" class="mt-6">
        <h2 id="money-heading" class="font-semibold text-gray-700 dark:text-gray-300">Money</h2>

        <dl class="mt-2 grid grid-cols-1 gap-3 sm:grid-cols-3">
            <div class="rounded border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-900 p-4">
                <dt class="text-gray-600 dark:text-gray-400">Subtotal</dt>
                <dd class="mt-1 text-2xl font-semibold tabular-nums">{{ $fulfillment->subtotal()->format() }}</dd>
            </div>
            <div class="rounded border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-900 p-4">
                <dt class="text-gray-600 dark:text-gray-400">Platform fee</dt>
                <dd class="mt-1 text-2xl font-semibold tabular-nums">{{ $fulfillment->fee()->format() }}</dd>
            </div>
            <div class="rounded border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-900 p-4">
                <dt class="text-gray-600 dark:text-gray-400">Seller net</dt>
                <dd class="mt-1 text-2xl font-semibold tabular-nums">{{ $fulfillment->net()->format() }}</dd>
            </div>
        </dl>
    </section>

    <section aria-labelledby="items-heading" class="mt-6">
        <h2 id="items-heading" class="font-semibold text-gray-700 dark:text-gray-300">Items</h2>

        <div class="mt-2 overflow-x-auto rounded border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-900">
            <table class="w-full text-left">
                <caption class="sr-only">The order lines this seller ships</caption>
                <thead class="border-b border-gray-300 dark:border-gray-700 bg-gray-50 dark:bg-gray-800/50">
                    <tr>
                        <th scope="col" class="px-4 py-2 font-semibold">Item</th>
                        <th scope="col" class="px-4 py-2 text-right font-semibold">Quantity</th>
                        <th scope="col" class="px-4 py-2 text-right font-semibold">Line total</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-gray-800">
                    @foreach ($fulfillment->order->items as $item)
                        <tr>
                            <th scope="row" class="px-4 py-2 font-normal">
                                <a href="{{ route('admin.listings.show', $item->listing_id) }}" class="underline">{{ $item->title }}</a>
                            </th>
                            <td class="px-4 py-2 text-right tabular-nums">{{ $item->quantity }}</td>
                            <td class="px-4 py-2 text-right tabular-nums">{{ $item->lineTotal()->format() }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </section>

    <section aria-labelledby="ledger-heading" class="mt-6">
        <h2 id="ledger-heading" class="font-semibold text-gray-700 dark:text-gray-300">Ledger</h2>

        @if ($fulfillment->ledgerEntries->isEmpty())
            <x-admin.nothing>Nothing in escrow for this fulfillment yet.</x-admin.nothing>
        @else
            <div class="mt-2 overflow-x-auto rounded border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-900">
                <table class="w-full text-left">
                    <caption class="sr-only">Every escrow movement this fulfillment wrote</caption>
                    <thead class="border-b border-gray-300 dark:border-gray-700 bg-gray-50 dark:bg-gray-800/50">
                        <tr>
                            <th scope="col" class="px-4 py-2 font-semibold">Type</th>
                            <th scope="col" class="px-4 py-2 font-semibold">Occurred</th>
                            <th scope="col" class="px-4 py-2 text-right font-semibold">Amount</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-gray-800">
                        @foreach ($fulfillment->ledgerEntries as $entry)
                            <tr>
                                <th scope="row" class="px-4 py-2 font-normal">{{ $entry->type->label() }}</th>
                                <td class="px-4 py-2">{{ $entry->occurred_at?->format('M j, Y g:ia') }}</td>
                                <td class="px-4 py-2 text-right tabular-nums">{{ $entry->amount()->format() }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>

    <section aria-labelledby="refund-heading" class="mt-6">
        <h2 id="refund-heading" class="font-semibold text-gray-700 dark:text-gray-300">Refund</h2>

        @if ($fulfillment->refund)
            <dl class="mt-2 rounded border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-900 p-4">
                <dt class="text-gray-600 dark:text-gray-400">Amount</dt>
                <dd class="mt-1 tabular-nums">{{ $fulfillment->refund->amount()->format() }}</dd>
                <dt class="mt-3 text-gray-600 dark:text-gray-400">Reason</dt>
                <dd class="mt-1">{{ $fulfillment->refund->reason }}</dd>
                <dt class="mt-3 text-gray-600 dark:text-gray-400">Issued by</dt>
                <dd class="mt-1">{{ $fulfillment->refund->issuerLabel() }}</dd>
            </dl>
        @else
            <x-admin.refund-form :fulfillment="$fulfillment" />
        @endif
    </section>
</x-layouts.admin>
