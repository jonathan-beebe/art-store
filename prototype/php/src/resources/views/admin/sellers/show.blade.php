<x-layouts.admin :title="$seller->displayName().' — Art Store admin'">
    <div class="flex flex-wrap items-center gap-4">
        <h1 class="text-xl font-semibold">{{ $seller->displayName() }}</h1>
        <a href="{{ route('admin.sellers.index') }}" class="ml-auto text-gray-700 underline">All sellers</a>
    </div>

    <dl class="mt-4 rounded border border-gray-300 bg-white p-4">
        <dt class="text-gray-600">Email</dt>
        <dd class="mt-1">{{ $seller->email }}</dd>
        <dt class="mt-3 text-gray-600">Joined</dt>
        <dd class="mt-1">{{ $seller->created_at?->format('M j, Y') }}</dd>
    </dl>

    <section aria-labelledby="balance-heading" class="mt-6">
        <h2 id="balance-heading" class="font-semibold text-gray-700">Escrow balance</h2>

        <x-admin.balance :balance="$balance" />
    </section>

    <section aria-labelledby="listings-heading" class="mt-6">
        <h2 id="listings-heading" class="font-semibold text-gray-700">Listings</h2>

        <dl class="mt-2 grid grid-cols-2 gap-3 sm:grid-cols-4">
            @foreach ($tally as $row)
                <div class="rounded border border-gray-300 bg-white p-4">
                    <dt class="text-gray-600">{{ $row->label() }}</dt>
                    <dd class="mt-1 text-2xl font-semibold tabular-nums">{{ $row->count }}</dd>
                </div>
            @endforeach
        </dl>

        <x-admin.listings-table :listings="$listings" :show-seller="false" caption="Every listing this seller holds" />
    </section>

    <section aria-labelledby="fulfillments-heading" class="mt-6">
        <h2 id="fulfillments-heading" class="font-semibold text-gray-700">Fulfillments</h2>

        <x-admin.fulfillments-table :fulfillments="$fulfillments" :show-seller="false" caption="Every fulfillment this seller shipped" />
    </section>

    <section aria-labelledby="payouts-heading" class="mt-6">
        <h2 id="payouts-heading" class="font-semibold text-gray-700">Payouts</h2>

        @if ($payouts->isEmpty())
            <x-admin.nothing>No payouts yet.</x-admin.nothing>
        @else
            <div class="mt-2 overflow-x-auto rounded border border-gray-300 bg-white">
                <table class="w-full text-left">
                    <caption class="sr-only">Every weekly payout this seller has been paid</caption>
                    <thead class="border-b border-gray-300 bg-gray-50">
                        <tr>
                            <th scope="col" class="px-4 py-2 font-semibold">Period</th>
                            <th scope="col" class="px-4 py-2 text-right font-semibold">Amount</th>
                            <th scope="col" class="px-4 py-2 font-semibold">Paid</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        @foreach ($payouts as $payout)
                            <tr>
                                <th scope="row" class="px-4 py-2 font-normal">
                                    {{ $payout->period_start?->format('M j, Y') }} – {{ $payout->period_end?->format('M j, Y') }}
                                </th>
                                <td class="px-4 py-2 text-right tabular-nums">{{ $payout->amount()->format() }}</td>
                                <td class="px-4 py-2">{{ $payout->paid_at?->format('M j, Y') }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>

    <section aria-labelledby="message-heading" class="mt-6 max-w-xl">
        <h2 id="message-heading" class="font-semibold text-gray-700">Message seller</h2>

        <x-messaging.body-form
            :action="route('admin.sellers.messages', $seller)"
            label="Message"
            class="mt-2 rounded border border-gray-300 bg-white p-4"
        />
    </section>
</x-layouts.admin>
