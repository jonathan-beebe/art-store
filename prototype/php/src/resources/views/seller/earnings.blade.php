<x-layouts.seller title="Earnings — Art Store seller">
    <h1 class="text-xl font-semibold">Earnings</h1>

    <section aria-labelledby="balances-heading" class="mt-6">
        <h2 id="balances-heading" class="font-semibold text-gray-700">Balances</h2>

        <dl class="mt-2 grid grid-cols-1 gap-3 sm:grid-cols-3">
            <div class="rounded border border-gray-300 bg-white p-4">
                <dt class="text-gray-600">Held in escrow</dt>
                <dd class="mt-1 text-2xl font-semibold tabular-nums">{{ $balance->held->format() }}</dd>
            </div>
            <div class="rounded border border-gray-300 bg-white p-4">
                <dt class="text-gray-600">Available</dt>
                <dd class="mt-1 text-2xl font-semibold tabular-nums">{{ $balance->available->format() }}</dd>
            </div>
            <div class="rounded border border-gray-300 bg-white p-4">
                <dt class="text-gray-600">Paid out</dt>
                <dd class="mt-1 text-2xl font-semibold tabular-nums">{{ $balance->paidOut->format() }}</dd>
            </div>
        </dl>
    </section>

    <section aria-labelledby="sales-heading" class="mt-6">
        <h2 id="sales-heading" class="font-semibold text-gray-700">Sales</h2>

        @if ($fulfillments->isEmpty())
            <p class="mt-2 rounded border border-gray-300 bg-white p-4 text-gray-600">No sales yet.</p>
        @else
            <div class="mt-2 overflow-x-auto rounded border border-gray-300 bg-white">
                <table class="w-full text-left">
                    <caption class="sr-only">Every sale, newest first</caption>
                    <thead class="border-b border-gray-300 bg-gray-50">
                        <tr>
                            <th scope="col" class="px-4 py-2 font-semibold">Date</th>
                            <th scope="col" class="px-4 py-2 font-semibold">Order</th>
                            <th scope="col" class="px-4 py-2 font-semibold">Items</th>
                            <th scope="col" class="px-4 py-2 text-right font-semibold">Subtotal</th>
                            <th scope="col" class="px-4 py-2 text-right font-semibold">Fee</th>
                            <th scope="col" class="px-4 py-2 text-right font-semibold">Net</th>
                            <th scope="col" class="px-4 py-2 font-semibold">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        @foreach ($fulfillments as $fulfillment)
                            <tr>
                                <td class="px-4 py-2">{{ $fulfillment->order->placed_at?->format('M j, Y') }}</td>
                                <th scope="row" class="px-4 py-2 font-normal">
                                    <a href="{{ route('seller.orders.show', $fulfillment->id) }}" class="underline">{{ $fulfillment->order_id }}</a>
                                </th>
                                <td class="px-4 py-2">{{ $fulfillment->order->items->pluck('title')->join(', ') }}</td>
                                <td class="px-4 py-2 text-right tabular-nums">{{ $fulfillment->subtotal() }}</td>
                                <td class="px-4 py-2 text-right tabular-nums">{{ $fulfillment->fee() }}</td>
                                <td class="px-4 py-2 text-right tabular-nums">{{ $fulfillment->net()->format() }}</td>
                                <td class="px-4 py-2">{{ $fulfillment->status->label() }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>

    <section aria-labelledby="payouts-heading" class="mt-6">
        <h2 id="payouts-heading" class="font-semibold text-gray-700">Payouts</h2>

        @if ($payouts->isEmpty())
            <p class="mt-2 rounded border border-gray-300 bg-white p-4 text-gray-600">No payouts yet.</p>
        @else
            <div class="mt-2 overflow-x-auto rounded border border-gray-300 bg-white">
                <table class="w-full text-left">
                    <caption class="sr-only">Weekly payouts, newest first</caption>
                    <thead class="border-b border-gray-300 bg-gray-50">
                        <tr>
                            <th scope="col" class="px-4 py-2 font-semibold">Period</th>
                            <th scope="col" class="px-4 py-2 text-right font-semibold">Amount</th>
                            <th scope="col" class="px-4 py-2 font-semibold">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        @foreach ($payouts as $payout)
                            <tr>
                                <th scope="row" class="px-4 py-2 font-normal">
                                    {{ $payout->period_start->format('M j, Y') }} to {{ $payout->period_end->format('M j, Y') }}
                                </th>
                                <td class="px-4 py-2 text-right tabular-nums">{{ $payout->amount()->format() }}</td>
                                <td class="px-4 py-2">{{ $payout->paid_at === null ? 'Pending' : 'Paid '.$payout->paid_at->format('M j, Y') }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif

        <form method="POST" action="{{ route('seller.earnings.payout') }}" class="mt-4">
            @csrf
            <button type="submit" class="rounded border border-gray-400 bg-white px-4 py-2 font-medium">Run weekly payout now</button>
            <span class="ml-2 text-gray-600">Debug control: settles every seller's released escrow for the last completed week.</span>
        </form>
    </section>
</x-layouts.seller>
