<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Statement {{ $figures->period->label() }} — Art Store seller</title>
    @vite(['resources/css/app.css'])
    <x-theme-css />
</head>
<body class="h-full bg-gray-100 font-sans text-sm text-gray-900 antialiased dark:bg-gray-950 dark:text-gray-100">
    <div class="mx-auto max-w-3xl px-6 py-10 print:max-w-none print:px-0 print:py-0">
        <div class="flex items-start justify-between gap-4 print:hidden">
            <a href="{{ route('seller.earnings') }}" class="text-sm/6 font-semibold text-indigo-600 hover:text-indigo-500 dark:text-indigo-400 dark:hover:text-indigo-300">&larr; Earnings</a>
            <button type="button" data-print class="rounded-md bg-indigo-600 px-3 py-2 text-sm font-semibold text-white shadow-xs hover:bg-indigo-500 dark:bg-indigo-500 dark:hover:bg-indigo-400">Print</button>
        </div>

        <div class="mt-6 rounded-lg border border-gray-200 bg-white p-8 print:border-0 print:p-0 dark:border-white/10 dark:bg-gray-900 print:dark:bg-white print:dark:text-black">
            <div class="flex items-start justify-between gap-4 border-b border-gray-200 pb-6 dark:border-white/10">
                <div>
                    <p class="text-xs font-medium tracking-wide text-gray-500 uppercase dark:text-gray-400">Statement</p>
                    <h1 class="mt-1 text-xl font-semibold text-gray-900 dark:text-white">{{ $seller->displayName() }}</h1>
                    <p class="mt-1 text-gray-500 dark:text-gray-400">{{ $figures->period->label() }}</p>
                </div>
                <div class="text-right">
                    @if ($settlement->status === \App\Domain\Seller\PeriodPayoutStatus::Paid)
                        <x-seller.status-badge tint="green">Paid {{ $settlement->paidAt?->format('M j, Y') }}</x-seller.status-badge>
                    @elseif ($settlement->status === \App\Domain\Seller\PeriodPayoutStatus::InProgress)
                        <x-seller.status-badge tint="gray">Period in progress</x-seller.status-badge>
                    @else
                        <x-seller.status-badge tint="gray">No payout</x-seller.status-badge>
                    @endif
                    <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">Generated {{ $generatedAt->format('M j, Y g:ia') }}</p>
                </div>
            </div>

            <dl class="mt-6 grid grid-cols-2 gap-4 sm:grid-cols-5">
                <div>
                    <dt class="text-xs text-gray-500 dark:text-gray-400">Orders</dt>
                    <dd class="text-lg font-semibold tabular-nums text-gray-900 dark:text-white">{{ $figures->orderCount }}</dd>
                </div>
                <div>
                    <dt class="text-xs text-gray-500 dark:text-gray-400">Sales</dt>
                    <dd class="text-lg font-semibold tabular-nums text-gray-900 dark:text-white">{{ $figures->sales->format() }}</dd>
                </div>
                <div>
                    <dt class="text-xs text-gray-500 dark:text-gray-400">Platform fees</dt>
                    <dd class="text-lg font-semibold tabular-nums text-gray-900 dark:text-white">{{ $figures->fees->format() }}</dd>
                </div>
                <div>
                    <dt class="text-xs text-gray-500 dark:text-gray-400">Refunds</dt>
                    <dd class="text-lg font-semibold tabular-nums text-gray-900 dark:text-white">{{ $figures->refunds->format() }}</dd>
                </div>
                <div>
                    <dt class="text-xs text-gray-500 dark:text-gray-400">Net</dt>
                    <dd class="text-lg font-semibold tabular-nums text-gray-900 dark:text-white">{{ $figures->net()->format() }}</dd>
                </div>
            </dl>

            <h2 class="mt-8 text-sm/6 font-semibold text-gray-900 dark:text-white">Orders this period</h2>
            @if (count($sales) === 0)
                <p class="mt-2 text-gray-600 dark:text-gray-400">No orders were placed this period.</p>
            @else
                <div class="mt-2 overflow-x-auto rounded border border-gray-300 dark:border-gray-700">
                    <table class="w-full text-left">
                        <thead class="border-b border-gray-300 bg-gray-50 dark:border-gray-700 dark:bg-gray-800/50 print:bg-transparent">
                            <tr>
                                <th scope="col" class="px-4 py-2 font-semibold">Date</th>
                                <th scope="col" class="px-4 py-2 font-semibold">Item</th>
                                <th scope="col" class="px-4 py-2 font-semibold">Buyer</th>
                                <th scope="col" class="px-4 py-2 text-right font-semibold">Subtotal</th>
                                <th scope="col" class="px-4 py-2 text-right font-semibold">Fee</th>
                                <th scope="col" class="px-4 py-2 text-right font-semibold">Net</th>
                                <th scope="col" class="px-4 py-2 font-semibold">Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-gray-800">
                            @foreach ($sales as $row)
                                <tr>
                                    <td class="px-4 py-2">{{ $row->placedAt->format('M j, Y') }}</td>
                                    <td class="px-4 py-2">{{ $row->itemLabel }}</td>
                                    <td class="px-4 py-2">{{ $row->buyerName }}</td>
                                    <td class="px-4 py-2 text-right tabular-nums">{{ $row->subtotal->format() }}</td>
                                    <td class="px-4 py-2 text-right tabular-nums">{{ $row->fee->format() }}</td>
                                    <td class="px-4 py-2 text-right font-semibold tabular-nums text-gray-900 dark:text-white">{{ $row->net->format() }}</td>
                                    <td class="px-4 py-2"><x-seller.status-badge :tint="$row->status->sellerBadgeTint()">{{ $row->status->label() }}</x-seller.status-badge></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>

    <script defer src="{{ asset('statement-print.js') }}"></script>
</body>
</html>
