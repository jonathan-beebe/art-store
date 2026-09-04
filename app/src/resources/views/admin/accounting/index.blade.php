<x-layouts.admin title="Accounting — Art Store admin">
    <h1 class="text-xl font-semibold">Accounting</h1>

    {{-- Headline figures: the shared-borders stat-tile grid — one hairline
         gap between cells rather than a border per card. --}}
    <div class="mt-4 grid grid-cols-2 gap-px overflow-hidden rounded-lg bg-stone-200 ring-1 ring-stone-200 sm:grid-cols-3 dark:bg-white/10 dark:ring-white/10" data-totals="platform">
        <x-stat-tile accent="stone" label="Held" data-cell="held">{{ $totals->held->format() }}</x-stat-tile>
        <x-stat-tile accent="stone" label="Available" data-cell="available">{{ $totals->available->format() }}</x-stat-tile>
        <x-stat-tile accent="stone" label="Paid out" data-cell="paid-out">{{ $totals->paidOut->format() }}</x-stat-tile>
        <x-stat-tile accent="stone" label="Refunded" data-cell="refunded">{{ $totals->refunded->format() }}</x-stat-tile>
        <x-stat-tile accent="stone" label="Fees earned" data-cell="fees-earned">{{ $totals->feesEarned->format() }}</x-stat-tile>
        <x-stat-tile accent="stone" label="Fees refunded" data-cell="fees-refunded">{{ $totals->feesRefunded->format() }}</x-stat-tile>
    </div>

    @if ($sellers->isEmpty())
        <x-admin.nothing class="mt-4">No sellers yet.</x-admin.nothing>
    @else
        <div class="mt-4 hidden overflow-x-auto rounded border border-stone-300 dark:border-stone-700 bg-white dark:bg-stone-900 sm:block">
            <table class="w-full text-left">
                <caption class="sr-only">Every seller's escrow reconciled against the ledger</caption>
                <thead class="border-b border-stone-300 dark:border-stone-700 bg-stone-50 dark:bg-stone-800/50">
                    <tr>
                        <th scope="col" class="px-4 py-2 font-semibold">Shop</th>
                        <th scope="col" class="px-4 py-2 text-right font-semibold">Held</th>
                        <th scope="col" class="px-4 py-2 text-right font-semibold">Available</th>
                        <th scope="col" class="px-4 py-2 text-right font-semibold">Paid out</th>
                        <th scope="col" class="px-4 py-2 text-right font-semibold">Refunded</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-stone-200 dark:divide-stone-800">
                    @foreach ($sellers as $seller)
                        @php($balance = $balances->of($seller->id))
                        <tr data-seller="{{ $seller->id }}">
                            <th scope="row" class="px-4 py-2 font-normal">
                                <a href="{{ route('admin.sellers.show', $seller) }}" class="font-medium underline">{{ $seller->displayName() }}</a>
                            </th>
                            <td class="px-4 py-2 text-right tabular-nums text-stone-500 dark:text-stone-400" data-cell="held">{{ $balance->held->format() }}</td>
                            <td class="px-4 py-2 text-right font-semibold tabular-nums text-stone-900 dark:text-white" data-cell="available">{{ $balance->available->format() }}</td>
                            <td class="px-4 py-2 text-right tabular-nums text-stone-500 dark:text-stone-400" data-cell="paid-out">{{ $balance->paidOut->format() }}</td>
                            <td class="px-4 py-2 text-right tabular-nums text-stone-500 dark:text-stone-400" data-cell="refunded">{{ $balance->refunded->format() }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <x-admin.card-list class="mt-4" caption="Every seller's escrow reconciled against the ledger">
            @foreach ($sellers as $seller)
                @php($balance = $balances->of($seller->id))
                <x-admin.card-row data-seller="{{ $seller->id }}">
                    <a href="{{ route('admin.sellers.show', $seller) }}" class="font-medium underline">{{ $seller->displayName() }}</a>
                    <div class="flex flex-wrap items-center gap-x-3 gap-y-1 tabular-nums text-stone-600 dark:text-stone-400">
                        <span data-cell="held">Held {{ $balance->held->format() }}</span>
                        <span class="font-semibold text-stone-900 dark:text-white" data-cell="available">Available {{ $balance->available->format() }}</span>
                        <span data-cell="paid-out">Paid {{ $balance->paidOut->format() }}</span>
                        <span data-cell="refunded">Refunded {{ $balance->refunded->format() }}</span>
                    </div>
                </x-admin.card-row>
            @endforeach
        </x-admin.card-list>
    @endif
</x-layouts.admin>
