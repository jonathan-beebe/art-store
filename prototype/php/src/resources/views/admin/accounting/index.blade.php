<x-layouts.admin title="Accounting — Art Store admin">
    <h1 class="text-xl font-semibold">Accounting</h1>

    <dl class="mt-4 grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-6" data-totals="platform">
        <div class="rounded border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-900 p-4">
            <dt class="text-gray-600 dark:text-gray-400">Held</dt>
            <dd class="mt-1 text-xl font-semibold tabular-nums" data-cell="held">{{ $totals->held->format() }}</dd>
        </div>
        <div class="rounded border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-900 p-4">
            <dt class="text-gray-600 dark:text-gray-400">Available</dt>
            <dd class="mt-1 text-xl font-semibold tabular-nums" data-cell="available">{{ $totals->available->format() }}</dd>
        </div>
        <div class="rounded border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-900 p-4">
            <dt class="text-gray-600 dark:text-gray-400">Paid out</dt>
            <dd class="mt-1 text-xl font-semibold tabular-nums" data-cell="paid-out">{{ $totals->paidOut->format() }}</dd>
        </div>
        <div class="rounded border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-900 p-4">
            <dt class="text-gray-600 dark:text-gray-400">Refunded</dt>
            <dd class="mt-1 text-xl font-semibold tabular-nums" data-cell="refunded">{{ $totals->refunded->format() }}</dd>
        </div>
        <div class="rounded border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-900 p-4">
            <dt class="text-gray-600 dark:text-gray-400">Fees earned</dt>
            <dd class="mt-1 text-xl font-semibold tabular-nums" data-cell="fees-earned">{{ $totals->feesEarned->format() }}</dd>
        </div>
        <div class="rounded border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-900 p-4">
            <dt class="text-gray-600 dark:text-gray-400">Fees refunded</dt>
            <dd class="mt-1 text-xl font-semibold tabular-nums" data-cell="fees-refunded">{{ $totals->feesRefunded->format() }}</dd>
        </div>
    </dl>

    @if ($sellers->isEmpty())
        <x-admin.nothing class="mt-4">No sellers yet.</x-admin.nothing>
    @else
        <div class="mt-4 overflow-x-auto rounded border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-900">
            <table class="w-full text-left">
                <caption class="sr-only">Every seller's escrow reconciled against the ledger</caption>
                <thead class="border-b border-gray-300 dark:border-gray-700 bg-gray-50 dark:bg-gray-800/50">
                    <tr>
                        <th scope="col" class="px-4 py-2 font-semibold">Shop</th>
                        <th scope="col" class="px-4 py-2 text-right font-semibold">Held</th>
                        <th scope="col" class="px-4 py-2 text-right font-semibold">Available</th>
                        <th scope="col" class="px-4 py-2 text-right font-semibold">Paid out</th>
                        <th scope="col" class="px-4 py-2 text-right font-semibold">Refunded</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-gray-800">
                    @foreach ($sellers as $seller)
                        @php($balance = $balances->of($seller->id))
                        <tr data-seller="{{ $seller->id }}">
                            <th scope="row" class="px-4 py-2 font-normal">
                                <a href="{{ route('admin.sellers.show', $seller) }}" class="font-medium underline">{{ $seller->displayName() }}</a>
                            </th>
                            <td class="px-4 py-2 text-right tabular-nums" data-cell="held">{{ $balance->held->format() }}</td>
                            <td class="px-4 py-2 text-right tabular-nums" data-cell="available">{{ $balance->available->format() }}</td>
                            <td class="px-4 py-2 text-right tabular-nums" data-cell="paid-out">{{ $balance->paidOut->format() }}</td>
                            <td class="px-4 py-2 text-right tabular-nums" data-cell="refunded">{{ $balance->refunded->format() }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</x-layouts.admin>
