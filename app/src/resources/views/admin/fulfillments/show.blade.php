<x-layouts.admin :title="'Fulfillment '.$fulfillment->id.' — Art Store admin'" mode="detail">
    @php
        $tint = $fulfillment->status->badgeTint();
    @endphp

    <x-slot:cells>
        <div class="flex items-baseline gap-2 border-b border-stone-200 px-6 py-4 dark:border-white/10">
            <h1 class="text-sm font-semibold text-stone-900 dark:text-stone-100">Fulfillments</h1>
            <span class="text-xs text-stone-500 dark:text-stone-400">{{ $cellFulfillmentsTotal }}</span>
        </div>
        <div class="flex-1 overflow-y-auto">
            <x-admin.fulfillments-cells :fulfillments="$cellFulfillments" :selected="$fulfillment" />
        </div>
        <x-admin.cell-footer :shown="$cellFulfillments->count()" :total="$cellFulfillmentsTotal" :route="route('admin.fulfillments.index')" />
    </x-slot:cells>

    <x-admin.back-link :route="route('admin.fulfillments.index')" label="Fulfillments" />

    <div class="flex flex-wrap items-start justify-between gap-4">
        <div>
            <p class="text-xs text-stone-500 dark:text-stone-400">{{ $fulfillment->id }} &middot; order {{ $fulfillment->order->id }}</p>
            <h1 class="mt-1 flex flex-wrap items-center gap-3 text-lg font-semibold text-stone-900 dark:text-stone-100">
                {{ $fulfillment->seller->displayName() }}
                <x-admin.status-pill :tint="$tint">{{ $fulfillment->status->label() }}</x-admin.status-pill>
            </h1>
        </div>

        <a href="{{ route('admin.fulfillments.index') }}" class="hidden text-sm text-stone-600 dark:text-stone-400 underline sm:inline">All fulfillments</a>
    </div>

    <dl class="mt-6 grid grid-cols-1 gap-x-8 border-t border-stone-200 dark:border-white/10 sm:grid-cols-2">
        <div class="flex justify-between gap-4 border-b border-stone-200 dark:border-white/10 py-3">
            <dt class="font-medium text-stone-900 dark:text-stone-100">Order</dt>
            <dd class="text-right text-stone-600 dark:text-stone-400"><a href="{{ route('admin.orders.show', $fulfillment->order) }}" class="underline">{{ $fulfillment->order->id }}</a></dd>
        </div>
        <div class="flex justify-between gap-4 border-b border-stone-200 dark:border-white/10 py-3">
            <dt class="font-medium text-stone-900 dark:text-stone-100">Customer</dt>
            <dd class="text-right text-stone-600 dark:text-stone-400"><a href="{{ route('admin.customers.show', $fulfillment->order->customer) }}" class="underline">{{ $fulfillment->order->customer->displayName() }}</a></dd>
        </div>
        <div class="flex justify-between gap-4 border-b border-stone-200 dark:border-white/10 py-3">
            <dt class="font-medium text-stone-900 dark:text-stone-100">Seller</dt>
            <dd class="text-right text-stone-600 dark:text-stone-400"><a href="{{ route('admin.sellers.show', $fulfillment->seller) }}" class="underline">{{ $fulfillment->seller->displayName() }}</a></dd>
        </div>
        <div class="flex justify-between gap-4 border-b border-stone-200 dark:border-white/10 py-3">
            <dt class="font-medium text-stone-900 dark:text-stone-100">Carrier</dt>
            <dd class="text-right text-stone-600 dark:text-stone-400">{{ $fulfillment->carrier ?? '—' }}</dd>
        </div>
        <div class="flex justify-between gap-4 border-b border-stone-200 dark:border-white/10 py-3">
            <dt class="font-medium text-stone-900 dark:text-stone-100">Tracking</dt>
            <dd class="text-right text-stone-600 dark:text-stone-400">{{ $fulfillment->tracking_number ?? '—' }}</dd>
        </div>
        <div class="flex justify-between gap-4 border-b border-stone-200 dark:border-white/10 py-3">
            <dt class="font-medium text-stone-900 dark:text-stone-100">Shipped</dt>
            <dd class="text-right text-stone-600 dark:text-stone-400">{{ $fulfillment->shipped_at?->format('M j, Y g:ia') ?? '—' }}</dd>
        </div>
        <div class="flex justify-between gap-4 border-b border-stone-200 dark:border-white/10 py-3">
            <dt class="font-medium text-stone-900 dark:text-stone-100">Delivered</dt>
            <dd class="text-right text-stone-600 dark:text-stone-400">{{ $fulfillment->delivered_at?->format('M j, Y g:ia') ?? '—' }}</dd>
        </div>
    </dl>

    <section aria-labelledby="money-heading" class="mt-6">
        <h2 id="money-heading" class="font-semibold text-stone-700 dark:text-stone-300">Money</h2>

        <dl class="mt-2 grid grid-cols-1 gap-3 sm:grid-cols-3">
            <div class="rounded-md border border-stone-300 dark:border-stone-700 bg-white dark:bg-stone-900 p-4">
                <dt class="text-stone-600 dark:text-stone-400">Subtotal</dt>
                <dd class="mt-1 text-2xl font-semibold tabular-nums text-stone-900 dark:text-stone-100">{{ $fulfillment->subtotal()->format() }}</dd>
            </div>
            <div class="rounded-md border border-stone-300 dark:border-stone-700 bg-white dark:bg-stone-900 p-4">
                <dt class="text-stone-600 dark:text-stone-400">Platform fee</dt>
                <dd class="mt-1 text-2xl font-semibold tabular-nums text-stone-900 dark:text-stone-100">{{ $fulfillment->fee()->format() }}</dd>
            </div>
            <div class="rounded-md border border-stone-300 dark:border-stone-700 bg-white dark:bg-stone-900 p-4">
                <dt class="text-stone-600 dark:text-stone-400">Seller net</dt>
                <dd class="mt-1 text-2xl font-semibold tabular-nums text-stone-900 dark:text-stone-100">{{ $fulfillment->net()->format() }}</dd>
            </div>
        </dl>
    </section>

    <section aria-labelledby="items-heading" class="mt-6">
        <h2 id="items-heading" class="font-semibold text-stone-700 dark:text-stone-300">Items</h2>

        <div class="mt-2 overflow-x-auto rounded-md border border-stone-300 dark:border-stone-700 bg-white dark:bg-stone-900">
            <table class="w-full text-left">
                <caption class="sr-only">The order lines this seller ships</caption>
                <thead class="border-b border-stone-300 dark:border-stone-700 bg-stone-50 dark:bg-stone-800/50">
                    <tr>
                        <th scope="col" class="px-4 py-2 font-semibold">Item</th>
                        <th scope="col" class="px-4 py-2 text-right font-semibold">Quantity</th>
                        <th scope="col" class="px-4 py-2 text-right font-semibold">Line total</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-stone-200 dark:divide-stone-800">
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
        <h2 id="ledger-heading" class="font-semibold text-stone-700 dark:text-stone-300">Ledger</h2>

        @if ($fulfillment->ledgerEntries->isEmpty())
            <x-admin.nothing>Nothing in escrow for this fulfillment yet.</x-admin.nothing>
        @else
            <div class="mt-2 overflow-x-auto rounded-md border border-stone-300 dark:border-stone-700 bg-white dark:bg-stone-900">
                <table class="w-full text-left">
                    <caption class="sr-only">Every escrow movement this fulfillment wrote</caption>
                    <thead class="border-b border-stone-300 dark:border-stone-700 bg-stone-50 dark:bg-stone-800/50">
                        <tr>
                            <th scope="col" class="px-4 py-2 font-semibold">Type</th>
                            <th scope="col" class="px-4 py-2 font-semibold">Occurred</th>
                            <th scope="col" class="px-4 py-2 text-right font-semibold">Amount</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-stone-200 dark:divide-stone-800">
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
        <h2 id="refund-heading" class="font-semibold text-stone-700 dark:text-stone-300">Refund</h2>

        @if ($fulfillment->refund)
            <dl class="mt-2 rounded-md border border-stone-300 dark:border-stone-700 bg-white dark:bg-stone-900 p-4">
                <dt class="text-stone-600 dark:text-stone-400">Amount</dt>
                <dd class="mt-1 tabular-nums text-stone-900 dark:text-stone-100">{{ $fulfillment->refund->amount()->format() }}</dd>
                <dt class="mt-3 text-stone-600 dark:text-stone-400">Reason</dt>
                <dd class="mt-1 text-stone-900 dark:text-stone-100">{{ $fulfillment->refund->reason }}</dd>
                <dt class="mt-3 text-stone-600 dark:text-stone-400">Issued by</dt>
                <dd class="mt-1 text-stone-900 dark:text-stone-100">{{ $fulfillment->refund->issuerLabel() }}</dd>
            </dl>
        @else
            <x-admin.refund-form :fulfillment="$fulfillment" />
        @endif
    </section>
</x-layouts.admin>
