<x-layouts.seller title="Earnings — Art Store seller">
    <div class="flex items-baseline justify-between">
        <h1 class="text-xl font-semibold">Earnings</h1>
    </div>

    <div class="mt-5 grid grid-cols-2 gap-px overflow-hidden rounded-lg bg-gray-200 ring-1 ring-gray-200 sm:grid-cols-4 dark:bg-white/10 dark:ring-white/10">
        <x-stat-tile accent="gray" label="Held in escrow">{{ $balance->held->format() }}</x-stat-tile>
        <x-stat-tile accent="gray" label="Released, awaiting payout">{{ $balance->available->format() }}</x-stat-tile>
        <x-stat-tile accent="gray" label="Paid out to date">{{ $balance->paidOut->format() }}</x-stat-tile>
        <x-stat-tile accent="gray" label="Open orders">{{ $openOrders }}</x-stat-tile>
    </div>

    @if ($payouts->isNotEmpty())
        @php
            $chartPayouts = $payouts->sortBy('period_start')->values()->slice(-8);
            $maxAmountCents = $chartPayouts->max('amount_cents') ?: 1;
            $peakPayoutId = $chartPayouts->sortByDesc('amount_cents')->first()?->id;
        @endphp

        <section aria-labelledby="paid-out-heading" class="mt-8">
            <h2 id="paid-out-heading" class="text-sm/6 font-semibold text-gray-900 dark:text-white">Paid out per week</h2>

            <div class="mt-2 rounded-lg border border-gray-200 px-6 pt-5 pb-3 dark:border-white/10">
                <div class="flex h-40 items-end gap-1 border-b border-gray-200 dark:border-white/10">
                    @foreach ($chartPayouts as $payout)
                        <div class="flex h-full flex-1 flex-col items-center justify-end gap-1">
                            @if ($payout->id === $peakPayoutId)
                                <span class="text-xs font-medium text-gray-700 dark:text-gray-300">{{ $payout->amount()->format() }}</span>
                            @endif
                            <div
                                class="w-3/5 max-w-10 rounded-t bg-indigo-600 dark:bg-indigo-500"
                                style="height: {{ max(6, (int) round($payout->amount_cents / $maxAmountCents * 100)) }}%"
                            ></div>
                        </div>
                    @endforeach
                </div>
                <div class="flex gap-1 pt-1.5">
                    @foreach ($chartPayouts as $payout)
                        <span class="flex-1 text-center text-xs text-gray-500 dark:text-gray-400">{{ $payout->period_start->format('M j') }}</span>
                    @endforeach
                </div>
            </div>
        </section>
    @endif

    <section aria-labelledby="sales-heading" class="mt-8">
        <h2 id="sales-heading" class="text-sm/6 font-semibold text-gray-900 dark:text-white">Sales</h2>

        @if ($fulfillments->isEmpty())
            <p class="mt-2 rounded border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-900 p-4 text-gray-600 dark:text-gray-400">No sales yet.</p>
        @else
            <div class="mt-2 overflow-x-auto">
                <table class="w-full border-collapse text-left">
                    <caption class="sr-only">Every sale, newest first</caption>
                    <thead>
                        <tr>
                            <th scope="col" class="border-b border-gray-200 py-2 pr-4 text-left text-sm/6 font-semibold text-gray-900 dark:border-white/10 dark:text-white">Date</th>
                            <th scope="col" class="border-b border-gray-200 py-2 pr-4 text-left text-sm/6 font-semibold text-gray-900 dark:border-white/10 dark:text-white">Order</th>
                            <th scope="col" class="border-b border-gray-200 py-2 pr-4 text-left text-sm/6 font-semibold text-gray-900 dark:border-white/10 dark:text-white">Items</th>
                            <th scope="col" class="border-b border-gray-200 py-2 pr-4 text-right text-sm/6 font-semibold text-gray-900 dark:border-white/10 dark:text-white">Subtotal</th>
                            <th scope="col" class="border-b border-gray-200 py-2 pr-4 text-right text-sm/6 font-semibold text-gray-900 dark:border-white/10 dark:text-white">Fee</th>
                            <th scope="col" class="border-b border-gray-200 py-2 pr-4 text-right text-sm/6 font-semibold text-gray-900 dark:border-white/10 dark:text-white">Net</th>
                            <th scope="col" class="border-b border-gray-200 py-2 text-left text-sm/6 font-semibold text-gray-900 dark:border-white/10 dark:text-white">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($fulfillments as $fulfillment)
                            <tr>
                                <td class="border-b border-gray-100 py-3 pr-4 text-sm/6 text-gray-500 dark:border-white/5 dark:text-gray-400">{{ $fulfillment->order->placed_at?->format('M j, Y') }}</td>
                                <th scope="row" class="border-b border-gray-100 py-3 pr-4 text-left text-sm/6 font-normal text-gray-500 dark:border-white/5 dark:text-gray-400">
                                    <a href="{{ route('seller.orders.show', $fulfillment->id) }}" class="underline">{{ $fulfillment->order_id }}</a>
                                </th>
                                <td class="border-b border-gray-100 py-3 pr-4 text-sm/6 text-gray-500 dark:border-white/5 dark:text-gray-400">{{ $fulfillment->order->items->pluck('title')->join(', ') }}</td>
                                <td class="border-b border-gray-100 py-3 pr-4 text-right text-sm/6 tabular-nums text-gray-500 dark:border-white/5 dark:text-gray-400">{{ $fulfillment->subtotal() }}</td>
                                <td class="border-b border-gray-100 py-3 pr-4 text-right text-sm/6 tabular-nums text-gray-500 dark:border-white/5 dark:text-gray-400">{{ $fulfillment->fee() }}</td>
                                <td class="border-b border-gray-100 py-3 pr-4 text-right text-sm/6 font-semibold tabular-nums text-gray-900 dark:border-white/5 dark:text-white">{{ $fulfillment->net()->format() }}</td>
                                <td class="border-b border-gray-100 py-3 text-sm/6 text-gray-500 dark:border-white/5 dark:text-gray-400">{{ $fulfillment->status->label() }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>

    <section aria-labelledby="refunds-heading" class="mt-8">
        <h2 id="refunds-heading" class="text-sm/6 font-semibold text-gray-900 dark:text-white">Refunds</h2>

        @if ($refunds->isEmpty())
            <p class="mt-2 rounded border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-900 p-4 text-gray-600 dark:text-gray-400">No refunds.</p>
        @else
            <div class="mt-2 overflow-x-auto">
                <table class="w-full border-collapse text-left">
                    <caption class="sr-only">Every refund taken back out of your escrow, newest first</caption>
                    <thead>
                        <tr>
                            <th scope="col" class="border-b border-gray-200 py-2 pr-4 text-left text-sm/6 font-semibold text-gray-900 dark:border-white/10 dark:text-white">Date</th>
                            <th scope="col" class="border-b border-gray-200 py-2 pr-4 text-left text-sm/6 font-semibold text-gray-900 dark:border-white/10 dark:text-white">Order</th>
                            <th scope="col" class="border-b border-gray-200 py-2 pr-4 text-left text-sm/6 font-semibold text-gray-900 dark:border-white/10 dark:text-white">Movement</th>
                            <th scope="col" class="border-b border-gray-200 py-2 text-right text-sm/6 font-semibold text-gray-900 dark:border-white/10 dark:text-white">Amount</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($refunds as $entry)
                            <tr>
                                <td class="border-b border-gray-100 py-3 pr-4 text-sm/6 text-gray-500 dark:border-white/5 dark:text-gray-400">{{ $entry->occurred_at?->format('M j, Y') }}</td>
                                <th scope="row" class="border-b border-gray-100 py-3 pr-4 text-left text-sm/6 font-normal text-gray-500 dark:border-white/5 dark:text-gray-400">
                                    <a href="{{ route('seller.orders.show', $entry->fulfillment_id) }}" class="underline">{{ $entry->fulfillment?->order_id }}</a>
                                </th>
                                <td class="border-b border-gray-100 py-3 pr-4 text-sm/6 text-gray-500 dark:border-white/5 dark:text-gray-400">{{ $entry->type->label() }}</td>
                                <td class="border-b border-gray-100 py-3 text-right text-sm/6 tabular-nums text-gray-500 dark:border-white/5 dark:text-gray-400">{{ $entry->amount()->format() }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>

    <section aria-labelledby="payouts-heading" class="mt-8">
        <h2 id="payouts-heading" class="text-sm/6 font-semibold text-gray-900 dark:text-white">Payout history</h2>

        @if ($payouts->isEmpty())
            <p class="mt-2 rounded border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-900 p-4 text-gray-600 dark:text-gray-400">No payouts yet.</p>
        @else
            <div class="mt-2 overflow-x-auto">
                <table class="w-full border-collapse text-left">
                    <caption class="sr-only">Weekly payouts, newest first</caption>
                    <thead>
                        <tr>
                            <th scope="col" class="border-b border-gray-200 py-2 pr-4 text-left text-sm/6 font-semibold text-gray-900 dark:border-white/10 dark:text-white">Period</th>
                            <th scope="col" class="border-b border-gray-200 py-2 pr-4 text-right text-sm/6 font-semibold text-gray-900 dark:border-white/10 dark:text-white">Amount</th>
                            <th scope="col" class="border-b border-gray-200 py-2 text-left text-sm/6 font-semibold text-gray-900 dark:border-white/10 dark:text-white">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($payouts as $payout)
                            <tr>
                                <th scope="row" class="border-b border-gray-100 py-3 pr-4 text-left text-sm/6 font-normal text-gray-500 dark:border-white/5 dark:text-gray-400">
                                    {{ $payout->period_start->format('M j, Y') }} to {{ $payout->period_end->format('M j, Y') }}
                                </th>
                                <td class="border-b border-gray-100 py-3 pr-4 text-right text-sm/6 font-semibold tabular-nums text-gray-900 dark:border-white/5 dark:text-white">{{ $payout->amount()->format() }}</td>
                                <td class="border-b border-gray-100 py-3 text-sm/6 text-gray-500 dark:border-white/5 dark:text-gray-400">{{ $payout->paid_at === null ? 'Pending' : 'Paid '.$payout->paid_at->format('M j, Y') }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>
</x-layouts.seller>
