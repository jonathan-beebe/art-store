<x-layouts.seller title="Earnings — Art Store seller">
    @php
        $current = $periods->current();
        $netStrip = $periods->netStrip(160);
        $salesChange = $periods->currentSalesChange();
        $salesChangeClass = match ($salesChange->direction) {
            \App\Domain\Analytics\ChangeDirection::Up => 'text-green-600 dark:text-green-400',
            \App\Domain\Analytics\ChangeDirection::Down => 'text-red-600 dark:text-red-400',
            \App\Domain\Analytics\ChangeDirection::Flat => 'text-gray-500 dark:text-gray-400',
        };
    @endphp

    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <h1 class="text-xl font-semibold">Earnings</h1>
            <p class="mt-0.5 text-gray-500 dark:text-gray-400">
                This payout period: {{ $current->period->start->format('M j') }}&ndash;{{ $current->period->end->format('M j, Y') }}.
                Periods run Monday to Sunday; the payout runs the Monday after.
            </p>
        </div>
        <a href="{{ route('seller.earnings.statements.show', $current->period->start->format('Y-m-d')) }}" class="rounded-md bg-white px-3 py-2 text-sm font-semibold text-gray-900 shadow-xs inset-ring inset-ring-gray-300 hover:bg-gray-50 dark:bg-white/10 dark:text-white dark:shadow-none dark:inset-ring-white/10 dark:hover:bg-white/20">Download statement</a>
    </div>

    <div class="mt-5 grid grid-cols-1 gap-5 lg:grid-cols-2">
        <section aria-labelledby="next-payout-heading" class="flex flex-col gap-4 rounded-lg border border-gray-200 bg-white p-6 dark:border-white/10 dark:bg-gray-900">
            <div class="flex items-start justify-between gap-4">
                <div>
                    <p id="next-payout-heading" class="text-xs font-medium tracking-wide text-gray-500 uppercase dark:text-gray-400">Next payout</p>
                    <p class="mt-1 text-3xl font-semibold tabular-nums text-gray-900 dark:text-white">{{ $nextPayout->estimate->amount->format() }}</p>
                    <p class="text-gray-500 dark:text-gray-400">Arrives {{ $nextPayout->estimate->payoutDate->format('l, M j') }}</p>
                </div>
                @if ($nextPayout->estimate->isCarryingNegative())
                    <x-seller.status-badge tint="red">Carried balance</x-seller.status-badge>
                @else
                    <x-seller.status-badge tint="green">On schedule</x-seller.status-badge>
                @endif
            </div>
            <dl class="border-t border-gray-200 pt-4 text-sm dark:border-white/10">
                <div class="flex items-center justify-between">
                    <dt class="text-gray-500 dark:text-gray-400">Released, awaiting payout</dt>
                    <dd class="font-semibold tabular-nums text-gray-900 dark:text-white">{{ $nextPayout->estimate->amount->format() }}</dd>
                </div>
                <p class="mt-1 text-xs text-gray-500 dark:text-gray-500">{{ $nextPayout->estimate->releasedOrderCount }} {{ $nextPayout->estimate->releasedOrderCount === 1 ? 'order' : 'orders' }} delivered since the last payout.</p>
            </dl>
            <p class="text-xs text-gray-500 dark:text-gray-500">Money releases when a buyer's delivery is confirmed. Anything released after Sunday lands in the following payout.</p>
        </section>

        <section aria-labelledby="held-heading" class="flex flex-col gap-4 rounded-lg border border-gray-200 bg-white p-6 dark:border-white/10 dark:bg-gray-900">
            <div>
                <p id="held-heading" class="text-xs font-medium tracking-wide text-gray-500 uppercase dark:text-gray-400">Held in escrow</p>
                <p class="mt-1 text-3xl font-semibold tabular-nums text-gray-900 dark:text-white">{{ $held->total->format() }}</p>
                <p class="text-gray-500 dark:text-gray-400">Across {{ count($held->orders) }} {{ count($held->orders) === 1 ? 'order' : 'orders' }} that {{ count($held->orders) === 1 ? 'has' : 'have' }} not been delivered yet.</p>
            </div>
            <ul class="flex flex-col divide-y divide-gray-100 border-t border-gray-200 dark:divide-white/5 dark:border-white/10">
                @forelse ($held->orders as $order)
                    <li>
                        <a href="{{ route('seller.orders.show', $order->fulfillmentId) }}" class="flex items-center justify-between gap-3 py-2.5 hover:bg-gray-50 dark:hover:bg-white/5">
                            <span class="min-w-0">
                                <span class="block truncate font-medium text-gray-900 dark:text-white">{{ $order->buyerName }} &middot; {{ $order->itemLabel }}</span>
                                <span class="block text-xs text-gray-500 dark:text-gray-400">
                                    {{ $order->state === \App\Domain\Seller\HeldState::InTransit ? 'In transit since '.$order->shippedAt?->format('M j') : 'Not yet shipped' }}
                                </span>
                            </span>
                            <span class="shrink-0 font-medium tabular-nums text-gray-900 dark:text-white">{{ $order->net->format() }}</span>
                        </a>
                    </li>
                @empty
                    <li class="py-2.5 text-gray-500 dark:text-gray-400">Nothing held right now.</li>
                @endforelse
            </ul>
            <a href="{{ route('seller.orders.index', ['lane' => 'progress']) }}" class="text-sm/6 font-semibold text-indigo-600 hover:text-indigo-500 dark:text-indigo-400 dark:hover:text-indigo-300">See every order in progress</a>
        </section>
    </div>

    <h2 class="mt-8 text-sm/6 font-semibold text-gray-900 dark:text-white">This period</h2>
    <div class="mt-2 grid grid-cols-2 gap-px overflow-hidden rounded-lg bg-gray-200 ring-1 ring-gray-200 sm:grid-cols-4 dark:bg-white/10 dark:ring-white/10">
        <x-stat-tile accent="gray" label="Sales">
            {{ $current->sales->format() }}
            <span class="ml-1 text-sm font-medium {{ $salesChangeClass }}">{{ $salesChange->text }}</span>
        </x-stat-tile>
        <x-stat-tile accent="gray" label="Platform fees">{{ $current->fees->format() }}</x-stat-tile>
        <x-stat-tile accent="gray" label="Refunds">{{ $current->refunds->format() }}</x-stat-tile>
        <x-stat-tile accent="gray" label="Net to you">{{ $current->net()->format() }}</x-stat-tile>
    </div>

    <div class="mt-5 grid grid-cols-1 items-start gap-5 lg:grid-cols-5">
        <section aria-labelledby="net-per-period-heading" class="rounded-lg border border-gray-200 px-6 pt-5 pb-3 lg:col-span-2 dark:border-white/10">
            <h3 id="net-per-period-heading" class="text-sm/6 font-semibold text-gray-900 dark:text-white">Net per period</h3>
            <p class="text-xs text-gray-500 dark:text-gray-400">Last eight periods, this one in progress</p>
            <div class="mt-4">
                <x-bar-strip :bars="$netStrip->bars" :baseline="$netStrip->baselinePx" :height="160" labelledby="net-per-period-heading" class="text-indigo-600 dark:text-indigo-500" />
            </div>
            <div class="flex gap-1 pt-1.5">
                @foreach ($periods->periods as $figures)
                    <span class="flex-1 text-center text-xs text-gray-500 dark:text-gray-400">{{ $figures->period->start->format('M j') }}</span>
                @endforeach
            </div>
        </section>

        <section aria-labelledby="period-sales-heading" class="overflow-hidden rounded-lg border border-gray-200 bg-white lg:col-span-3 dark:border-white/10 dark:bg-gray-900">
            <h3 id="period-sales-heading" class="sr-only">This period's sales</h3>
            @if (count($currentSales) === 0)
                <p class="p-4 text-gray-600 dark:text-gray-400">No sales yet this period.</p>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full text-left">
                        <thead class="border-b border-gray-300 bg-gray-50 dark:border-gray-700 dark:bg-gray-800/50">
                            <tr>
                                <th scope="col" class="px-4 py-2 font-semibold">Sale</th>
                                <th scope="col" class="px-4 py-2 font-semibold">Buyer</th>
                                <th scope="col" class="px-4 py-2 text-right font-semibold">Subtotal</th>
                                <th scope="col" class="px-4 py-2 text-right font-semibold">Fee</th>
                                <th scope="col" class="px-4 py-2 text-right font-semibold">Net</th>
                                <th scope="col" class="px-4 py-2 font-semibold">Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-gray-800">
                            @foreach ($currentSales as $row)
                                <tr>
                                    <td class="px-4 py-2">
                                        <a href="{{ route('seller.orders.show', $row->fulfillmentId) }}" class="block max-w-[200px] truncate font-medium text-gray-900 hover:underline dark:text-white">{{ $row->itemLabel }}</a>
                                        <p class="text-xs text-gray-500 dark:text-gray-400">{{ $row->placedAt->format('M j, Y') }}</p>
                                    </td>
                                    <td class="px-4 py-2">{{ $row->buyerName }}</td>
                                    <td class="px-4 py-2 text-right tabular-nums">{{ $row->subtotal->format() }}</td>
                                    <td class="px-4 py-2 text-right tabular-nums">{{ $row->fee->format() }}</td>
                                    <td class="px-4 py-2 text-right font-semibold tabular-nums text-gray-900 dark:text-white print:dark:text-black">{{ $row->net->format() }}</td>
                                    <td class="px-4 py-2"><x-seller.status-badge :tint="$row->status->badgeTint()">{{ $row->status->label() }}</x-seller.status-badge></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </section>
    </div>

    <h2 class="mt-8 text-sm/6 font-semibold text-gray-900 dark:text-white">Past periods</h2>
    <div class="mt-2 overflow-hidden rounded-lg border border-gray-200 bg-white dark:border-white/10 dark:bg-gray-900">
        <div class="overflow-x-auto">
            <table class="w-full text-left">
                <thead class="border-b border-gray-300 bg-gray-50 dark:border-gray-700 dark:bg-gray-800/50">
                    <tr>
                        <th scope="col" class="px-4 py-2 font-semibold">Period</th>
                        <th scope="col" class="px-4 py-2 text-right font-semibold">Orders</th>
                        <th scope="col" class="px-4 py-2 text-right font-semibold">Sales</th>
                        <th scope="col" class="px-4 py-2 text-right font-semibold">Fees</th>
                        <th scope="col" class="px-4 py-2 text-right font-semibold">Refunds</th>
                        <th scope="col" class="px-4 py-2 text-right font-semibold">Net</th>
                        <th scope="col" class="px-4 py-2 font-semibold">Payout</th>
                        <th scope="col" class="px-4 py-2"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-gray-800">
                    @foreach ($periods->past() as $figures)
                        @php $settlement = $periods->settlementOf($figures); @endphp
                        <tr>
                            <td class="px-4 py-2 font-medium text-gray-900 dark:text-white">{{ $figures->period->label() }}</td>
                            <td class="px-4 py-2 text-right tabular-nums">{{ $figures->orderCount }}</td>
                            <td class="px-4 py-2 text-right tabular-nums">{{ $figures->sales->format() }}</td>
                            <td class="px-4 py-2 text-right tabular-nums">{{ $figures->fees->format() }}</td>
                            <td class="px-4 py-2 text-right tabular-nums">{{ $figures->refunds->format() }}</td>
                            <td class="px-4 py-2 text-right font-semibold tabular-nums text-gray-900 dark:text-white print:dark:text-black">{{ $figures->net()->format() }}</td>
                            <td class="px-4 py-2">
                                @if ($settlement->status === \App\Domain\Seller\PeriodPayoutStatus::Paid)
                                    <x-seller.status-badge tint="green">Paid {{ $settlement->paidAt?->format('M j') }}</x-seller.status-badge>
                                @else
                                    <x-seller.status-badge tint="gray">No payout</x-seller.status-badge>
                                @endif
                            </td>
                            <td class="px-4 py-2 text-right">
                                <a href="{{ route('seller.earnings.statements.show', $figures->period->start->format('Y-m-d')) }}" class="text-sm/6 font-semibold text-indigo-600 hover:text-indigo-500 dark:text-indigo-400 dark:hover:text-indigo-300">Statement<span class="sr-only"> for {{ $figures->period->label() }}</span></a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</x-layouts.seller>
