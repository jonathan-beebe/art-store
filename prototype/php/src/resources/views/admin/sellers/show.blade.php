<x-layouts.admin :title="$seller->displayName().' — Art Store admin'" mode="detail">
    <x-slot:cells>
        <div class="flex items-baseline gap-2 border-b border-stone-200 p-3 dark:border-stone-800">
            <h1 class="text-sm font-semibold">Sellers</h1>
            <span class="text-xs text-stone-500 dark:text-stone-400">{{ $cellSellersTotal }}</span>
        </div>
        <div class="flex-1 overflow-y-auto">
            <x-admin.sellers-cells :sellers="$cellSellers" :balances="$cellBalances" :selected="$seller" />
        </div>
        <x-admin.cell-footer :shown="$cellSellers->count()" :total="$cellSellersTotal" :route="route('admin.sellers.index')" />
    </x-slot:cells>

    <x-admin.back-link :route="route('admin.sellers.index')" label="Sellers" />

    <div class="flex flex-wrap items-start justify-between gap-4">
        <div class="flex flex-col gap-1">
            <p class="text-xs text-stone-500 dark:text-stone-400">{{ $seller->id }} &middot; joined {{ $seller->created_at?->format('M j, Y') }}</p>
            <h1 class="text-xl font-semibold text-stone-900 dark:text-stone-100">{{ $seller->displayName() }}</h1>
        </div>
        <a href="{{ route('admin.sellers.index') }}" class="hidden text-stone-700 dark:text-stone-300 underline sm:inline">All sellers</a>
    </div>

    <dl class="mt-6 grid grid-cols-1 gap-x-8 border-t border-stone-200 dark:border-stone-800 sm:grid-cols-2">
        <div class="flex justify-between gap-4 border-b border-stone-200 dark:border-stone-800 py-3">
            <dt class="font-medium text-stone-900 dark:text-stone-100">Email</dt>
            <dd class="text-right text-stone-600 dark:text-stone-400">{{ $seller->email }}</dd>
        </div>
        <div class="flex justify-between gap-4 border-b border-stone-200 dark:border-stone-800 py-3">
            <dt class="font-medium text-stone-900 dark:text-stone-100">Joined</dt>
            <dd class="text-right text-stone-600 dark:text-stone-400">{{ $seller->created_at?->format('M j, Y') }}</dd>
        </div>
    </dl>

    <section aria-labelledby="balance-heading" class="mt-6">
        <h2 id="balance-heading" class="font-semibold text-stone-700 dark:text-stone-300">Escrow balance</h2>

        <x-admin.balance :balance="$balance" />
    </section>

    <section aria-labelledby="listings-heading" class="mt-6">
        <h2 id="listings-heading" class="font-semibold text-stone-700 dark:text-stone-300">Listings</h2>

        <dl class="mt-2 grid grid-cols-2 gap-3 sm:grid-cols-4">
            @foreach ($tally as $row)
                <div class="rounded border border-stone-300 dark:border-stone-700 bg-white dark:bg-stone-900 p-4">
                    <dt class="text-stone-600 dark:text-stone-400">{{ $row->label() }}</dt>
                    <dd class="mt-1 text-2xl font-semibold tabular-nums">{{ $row->count }}</dd>
                </div>
            @endforeach
        </dl>

        <x-admin.listings-table :listings="$listings" :show-seller="false" caption="Every listing this seller holds" />
    </section>

    <section aria-labelledby="fulfillments-heading" class="mt-6">
        <h2 id="fulfillments-heading" class="font-semibold text-stone-700 dark:text-stone-300">Fulfillments</h2>

        <x-admin.fulfillments-table :fulfillments="$fulfillments" :show-seller="false" caption="Every fulfillment this seller shipped" />
    </section>

    <section aria-labelledby="payouts-heading" class="mt-6">
        <h2 id="payouts-heading" class="font-semibold text-stone-700 dark:text-stone-300">Payouts</h2>

        <x-admin.payouts-table :payouts="$payouts" :show-seller="false" caption="Every weekly payout this seller has been paid" />
    </section>

    <section id="message-seller-form" aria-labelledby="message-heading" class="mt-6 max-w-xl scroll-mt-6">
        <h2 id="message-heading" class="font-semibold text-stone-700 dark:text-stone-300">Message seller</h2>

        <x-messaging.open-thread-form
            :action="route('admin.sellers.messages', $seller)"
            context-field="fulfillment"
            context-label="Order"
            :context-options="$fulfillments->mapWithKeys(fn ($fulfillment) => [$fulfillment->id => 'Order '.$fulfillment->order_id])"
            :selected-context-id="request()->query('fulfillment')"
        />
    </section>
</x-layouts.admin>
