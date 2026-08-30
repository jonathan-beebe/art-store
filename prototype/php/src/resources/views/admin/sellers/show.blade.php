<x-layouts.admin :title="$seller->displayName().' — Art Store admin'">
    <x-admin.back-link :route="route('admin.sellers.index')" label="Sellers" />

    <div class="flex flex-wrap items-center gap-4">
        <h1 class="text-xl font-semibold">{{ $seller->displayName() }}</h1>
        <a href="{{ route('admin.sellers.index') }}" class="ml-auto hidden text-gray-700 dark:text-gray-300 underline sm:inline">All sellers</a>
    </div>

    <dl class="mt-4 rounded border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-900 p-4">
        <dt class="text-gray-600 dark:text-gray-400">Email</dt>
        <dd class="mt-1">{{ $seller->email }}</dd>
        <dt class="mt-3 text-gray-600 dark:text-gray-400">Joined</dt>
        <dd class="mt-1">{{ $seller->created_at?->format('M j, Y') }}</dd>
    </dl>

    <section aria-labelledby="balance-heading" class="mt-6">
        <h2 id="balance-heading" class="font-semibold text-gray-700 dark:text-gray-300">Escrow balance</h2>

        <x-admin.balance :balance="$balance" />
    </section>

    <section aria-labelledby="listings-heading" class="mt-6">
        <h2 id="listings-heading" class="font-semibold text-gray-700 dark:text-gray-300">Listings</h2>

        <dl class="mt-2 grid grid-cols-2 gap-3 sm:grid-cols-4">
            @foreach ($tally as $row)
                <div class="rounded border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-900 p-4">
                    <dt class="text-gray-600 dark:text-gray-400">{{ $row->label() }}</dt>
                    <dd class="mt-1 text-2xl font-semibold tabular-nums">{{ $row->count }}</dd>
                </div>
            @endforeach
        </dl>

        <x-admin.listings-table :listings="$listings" :show-seller="false" caption="Every listing this seller holds" />
    </section>

    <section aria-labelledby="fulfillments-heading" class="mt-6">
        <h2 id="fulfillments-heading" class="font-semibold text-gray-700 dark:text-gray-300">Fulfillments</h2>

        <x-admin.fulfillments-table :fulfillments="$fulfillments" :show-seller="false" caption="Every fulfillment this seller shipped" />
    </section>

    <section aria-labelledby="payouts-heading" class="mt-6">
        <h2 id="payouts-heading" class="font-semibold text-gray-700 dark:text-gray-300">Payouts</h2>

        <x-admin.payouts-table :payouts="$payouts" :show-seller="false" caption="Every weekly payout this seller has been paid" />
    </section>

    <section aria-labelledby="message-heading" class="mt-6 max-w-xl">
        <h2 id="message-heading" class="font-semibold text-gray-700 dark:text-gray-300">Message seller</h2>

        <x-messaging.body-form
            :action="route('admin.sellers.messages', $seller)"
            label="Message"
            class="mt-2 rounded border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-900 p-4"
        />
    </section>
</x-layouts.admin>
